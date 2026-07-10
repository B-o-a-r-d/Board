<?php

use App\Models\Attachment;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Phase 1 of the security remediation: user media is served from a PRIVATE disk
 * through MediaController, with board-level authorization and anti-XSS headers.
 */

test('a board member can view an attachment with anti-XSS headers', function () {
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $attachment = Attachment::factory()->create([
        'card_id' => $card->id,
        'disk' => 'local',
        'path' => 'attachments/'.$board->id.'/photo.png',
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($attachment->path, 'binary');

    $this->actingAs($owner)
        ->get(route('attachments.show', $attachment))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->assertHeader('Content-Type', 'image/png');
});

test('a non-member cannot view an attachment', function () {
    Storage::fake('local');
    ['card' => $card] = makeCardContext();
    $outsider = User::factory()->create();

    $attachment = Attachment::factory()->create([
        'card_id' => $card->id,
        'disk' => 'local',
        'path' => 'attachments/'.$card->board_id.'/secret.png',
    ]);
    Storage::disk('local')->put($attachment->path, 'binary');

    $this->actingAs($outsider)
        ->get(route('attachments.show', $attachment))
        ->assertForbidden();
});

test('a guest is redirected to login for an attachment', function () {
    Storage::fake('local');
    ['card' => $card] = makeCardContext();

    $attachment = Attachment::factory()->create([
        'card_id' => $card->id,
        'disk' => 'local',
        'path' => 'attachments/'.$card->board_id.'/secret.png',
    ]);
    Storage::disk('local')->put($attachment->path, 'binary');

    $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
});

test('a malicious SVG attachment is served sandboxed so its script cannot run', function () {
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
    $attachment = Attachment::factory()->create([
        'card_id' => $card->id,
        'disk' => 'local',
        'path' => 'attachments/'.$board->id.'/evil.svg',
        'name' => 'evil.svg',
        'mime_type' => 'image/svg+xml',
    ]);
    Storage::disk('local')->put($attachment->path, $svg);

    $response = $this->actingAs($owner)->get(route('attachments.show', $attachment))->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    // Inline (not an executable navigation context beyond the sandbox), never public.
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('the attachment url accessor points at the authorized route, not the public disk', function () {
    $attachment = Attachment::factory()->create(['disk' => 'local']);

    expect($attachment->url)
        ->toContain('/media/attachments/')
        ->not->toContain('/storage/');
});

test('a board background is reachable by a guest holding the share token', function () {
    Storage::fake('local');
    config(['board.public_sharing' => true]);

    $board = Board::factory()->create(['background_image' => 'board-backgrounds/1/bg.jpg']);
    $board->enableSharing();
    Storage::disk('local')->put($board->background_image, 'binary');

    // Valid token → served.
    $this->get(route('media.board-background', ['board' => $board, 't' => $board->share_token]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Missing / wrong token → denied.
    $this->get(route('media.board-background', ['board' => $board]))->assertForbidden();
    $this->get(route('media.board-background', ['board' => $board, 't' => 'wrong']))->assertForbidden();
});

test('board background is denied when public sharing is disabled', function () {
    Storage::fake('local');
    config(['board.public_sharing' => false]);

    $board = Board::factory()->create(['background_image' => 'board-backgrounds/1/bg.jpg']);
    $board->enableSharing();
    Storage::disk('local')->put($board->background_image, 'binary');

    $this->get(route('media.board-background', ['board' => $board, 't' => $board->share_token]))
        ->assertForbidden();
});

test('an authenticated user can load an avatar, a guest cannot', function () {
    Storage::fake('local');
    $viewer = User::factory()->create();
    $target = User::factory()->create(['avatar_path' => 'avatars/a.png']);
    Storage::disk('local')->put($target->avatar_path, 'binary');

    // Guest first (no prior authentication leaking into the request).
    $this->get(route('media.avatar', $target))->assertRedirect(route('login'));
    $this->actingAs($viewer)->get(route('media.avatar', $target))->assertOk();
});
