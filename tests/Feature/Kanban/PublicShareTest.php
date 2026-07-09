<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User}
 */
function makeShareBoard(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return compact('board', 'owner');
}

test('a board admin can enable and disable public sharing', function () {
    ['board' => $board, 'owner' => $owner] = makeShareBoard();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('toggleShare');
    $token = $board->fresh()->share_token;
    expect($token)->not->toBeNull();

    // Re-enabling is idempotent (same token stays until disabled).
    $component->call('openShare')->assertSet('showShare', true);

    $component->call('toggleShare');
    expect($board->fresh()->share_token)->toBeNull();
});

test('a plain member cannot toggle sharing', function () {
    ['board' => $board] = makeShareBoard();
    $member = User::factory()->create();
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)
        ->test(Show::class, ['board' => $board])
        ->call('toggleShare')
        ->assertForbidden();

    expect($board->fresh()->share_token)->toBeNull();
});

test('the public share page renders the board read-only for guests', function () {
    ['board' => $board] = makeShareBoard();
    $board->enableSharing();

    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'À faire']);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte publique']);

    $this->get(route('boards.public', ['token' => $board->share_token]))
        ->assertOk()
        ->assertSee('Carte publique')
        ->assertSee('Lecture seule')
        ->assertDontSee('Ajouter une carte');
});

test('the public share page exposes Open Graph social meta tags', function () {
    ['board' => $board] = makeShareBoard();
    $board->update(['name' => 'Roadmap Q3', 'description' => 'Notre feuille de route trimestrielle.']);
    $board->enableSharing();

    $this->get(route('boards.public', ['token' => $board->share_token]))
        ->assertOk()
        ->assertSee('property="og:title" content="Roadmap Q3"', escape: false)
        ->assertSee('property="og:url" content="'.route('boards.public', $board->share_token).'"', escape: false)
        ->assertSee('property="og:image"', escape: false)
        ->assertSee('name="twitter:card" content="summary_large_image"', escape: false)
        ->assertSee('Notre feuille de route trimestrielle.', escape: false);
});

test('an unknown or disabled token returns 404', function () {
    ['board' => $board] = makeShareBoard();

    $this->get(route('boards.public', ['token' => 'does-not-exist']))->assertNotFound();

    $board->enableSharing();
    $token = $board->share_token;
    $board->disableSharing();

    $this->get(route('boards.public', ['token' => $token]))->assertNotFound();
});

test('the guest presence endpoint signs the presence channel', function () {
    ['board' => $board] = makeShareBoard();
    $board->enableSharing();
    $token = $board->share_token;

    config([
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
    ]);

    $channel = 'presence-board-presence.'.$board->id;

    $response = $this->postJson(route('boards.public.presence', ['token' => $token]), [
        'socket_id' => '123.456',
        'channel_name' => $channel,
    ])->assertOk();

    $data = $response->json();

    $expected = 'test-key:'.hash_hmac('sha256', '123.456:'.$channel.':'.$data['channel_data'], 'test-secret');

    expect($data['auth'])->toBe($expected);

    $channelData = json_decode($data['channel_data'], true);
    expect($channelData['user_id'])->toBeString()
        ->and($channelData['user_info']['name'])->toStartWith('Visiteur ')
        ->and($channelData['user_info']['color'])->toStartWith('#')
        ->and($channelData['user_info']['guest'])->toBeTrue();
});

test('the presence endpoint refuses a channel that is not its token', function () {
    ['board' => $board] = makeShareBoard();
    $board->enableSharing();

    $this->postJson(route('boards.public.presence', ['token' => $board->share_token]), [
        'socket_id' => '1.1',
        'channel_name' => 'presence-board-presence.999999',
    ])->assertForbidden();
});

test('sharing is unavailable when disabled in config', function () {
    ['board' => $board, 'owner' => $owner] = makeShareBoard();
    $board->enableSharing();

    config(['board.public_sharing' => false]);

    $this->get(route('boards.public', ['token' => $board->share_token]))->assertNotFound();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('openShare')
        ->assertStatus(404);
});
