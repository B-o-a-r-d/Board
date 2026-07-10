<?php

use App\Support\SafeHttp;
use App\Support\SsrfException;
use Illuminate\Support\Facades\Http;

/*
 * Phase 5: outbound fetches survive redirect- and rebinding-based SSRF. Public IP
 * literals are used so the guard runs without real DNS in tests.
 */

test('it rejects an unsafe initial url before any request', function () {
    Http::fake();

    expect(fn () => SafeHttp::get('http://127.0.0.1/admin'))->toThrow(SsrfException::class);
    expect(fn () => SafeHttp::get('http://169.254.169.254/meta'))->toThrow(SsrfException::class);
    expect(fn () => SafeHttp::get('ftp://example.com/x'))->toThrow(SsrfException::class);

    Http::assertNothingSent();
});

test('it blocks a redirect that points at an internal host', function () {
    Http::fake([
        'http://93.184.216.34/go' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
    ]);

    expect(fn () => SafeHttp::get('http://93.184.216.34/go'))->toThrow(SsrfException::class);

    // The internal hop is never actually requested.
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '169.254.169.254'));
});

test('it follows a safe redirect and returns the final response', function () {
    Http::fake([
        'http://93.184.216.34/a' => Http::response('', 302, ['Location' => 'http://93.184.216.34/b']),
        'http://93.184.216.34/b' => Http::response('final', 200),
    ]);

    $response = SafeHttp::get('http://93.184.216.34/a');

    expect($response->status())->toBe(200)
        ->and($response->body())->toBe('final');
});

test('it stops after too many redirects and returns the last hop', function () {
    Http::fake([
        'http://93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://93.184.216.34/loop']),
    ]);

    $response = SafeHttp::get('http://93.184.216.34/start');

    expect($response->redirect())->toBeTrue();
});

test('it honours an allow-listed internal host', function () {
    Http::fake(['http://10.0.0.5/ok' => Http::response('ok', 200)]);

    $response = SafeHttp::get('http://10.0.0.5/ok', allowedHosts: ['10.0.0.5']);

    expect($response->body())->toBe('ok');
});
