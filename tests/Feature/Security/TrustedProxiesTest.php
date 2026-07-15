<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The app must only honour X-Forwarded-For from a trusted proxy. Otherwise a
 * client reaching the app directly could spoof its IP and defeat the IP-based
 * login throttle. Default trust is the private ranges (Docker/Traefik + local).
 */
beforeEach(function () {
    Route::get('/__test_ip', fn (Request $request) => $request->ip())->middleware('web');
});

test('a spoofed X-Forwarded-For from an untrusted (public) client is ignored', function () {
    $response = $this->call('GET', '/__test_ip', server: [
        'REMOTE_ADDR' => '203.0.113.9',            // untrusted public peer
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',        // attacker-controlled header
    ]);

    // The real peer wins; the forwarded header is not trusted.
    expect($response->getContent())->toBe('203.0.113.9');
});

test('X-Forwarded-For from a private-range proxy is honoured', function () {
    $response = $this->call('GET', '/__test_ip', server: [
        'REMOTE_ADDR' => '10.1.2.3',                // trusted (private range) proxy
        'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
    ]);

    expect($response->getContent())->toBe('1.2.3.4');
});
