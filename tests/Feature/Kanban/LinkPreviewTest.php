<?php

use App\Livewire\Cards\CardDetail;
use App\Models\LinkPreview;
use App\Services\UrlPreviewService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('the service parses open graph tags and caches the result', function () {
    Http::fake(['*' => Http::response(
        '<html><head>'
        .'<meta property="og:title" content="Titre OG">'
        .'<meta property="og:description" content="Une description">'
        .'<meta property="og:image" content="https://cdn.example/img.png">'
        .'<meta property="og:site_name" content="Example">'
        .'</head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    $service = app(UrlPreviewService::class);

    // Public IP literal avoids DNS in tests while passing the SSRF guard.
    $preview = $service->preview('http://93.184.216.34/page');

    expect($preview)->not->toBeNull()
        ->and($preview->title)->toBe('Titre OG')
        ->and($preview->description)->toBe('Une description')
        ->and($preview->image)->toBe('https://cdn.example/img.png')
        ->and($preview->site_name)->toBe('Example')
        ->and($preview->ok)->toBeTrue();

    // Second call is served from cache — no additional request.
    $service->preview('http://93.184.216.34/page');

    Http::assertSentCount(1);
    expect(LinkPreview::count())->toBe(1);
});

test('the service refuses private and reserved hosts (SSRF guard)', function () {
    Http::fake();

    $service = app(UrlPreviewService::class);

    expect($service->preview('http://127.0.0.1/admin'))->toBeNull()
        ->and($service->preview('http://169.254.169.254/latest/meta-data'))->toBeNull()
        ->and($service->preview('ftp://example.com/file'))->toBeNull();

    Http::assertNothingSent();
});

test('the service refuses a redirect to an internal host', function () {
    Http::fake([
        'http://93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
    ]);

    expect(app(UrlPreviewService::class)->preview('http://93.184.216.34/page'))->toBeNull();

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '169.254.169.254'));
});

test('the service extracts at most three urls from a text block', function () {
    $urls = app(UrlPreviewService::class)->extractUrls(
        'a https://a.com b https://b.com c https://c.com d https://d.com',
    );

    expect($urls)->toBe(['https://a.com', 'https://b.com', 'https://c.com']);
});

test('hiding a description embed persists on the card (shared with everyone)', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('toggleDescriptionPreview', 'https://example.com/x');
    expect($card->fresh()->hidden_previews)->toBe(['https://example.com/x']);

    // Toggling again re-shows it.
    $component->call('toggleDescriptionPreview', 'https://example.com/x');
    expect($card->fresh()->hidden_previews)->toBe([]);
});

test('hiding a comment embed persists on the comment', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $comment = $card->comments()->create(['user_id' => $owner->id, 'body' => 'https://example.com/y']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleCommentPreview', $comment->id, 'https://example.com/y');

    expect($comment->fresh()->hidden_previews)->toBe(['https://example.com/y']);
});

test('card detail renders a link preview for a description url', function () {
    Http::fake(['*' => Http::response(
        '<html><head><title>Aperçu de secours</title></head></html>',
        200,
        ['Content-Type' => 'text/html'],
    )]);

    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['description' => 'Voir http://93.184.216.34/x']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertSee('Aperçu de secours');
});
