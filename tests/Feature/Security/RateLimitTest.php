<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Brute-force / abuse protection on the auth POST endpoints Fortify does not
 * throttle itself, plus a bound on the authenticated API and MCP surfaces.
 */
test('the password-reset request endpoint is throttled per email and IP', function () {
    $statuses = [];

    for ($i = 0; $i < 7; $i++) {
        $statuses[] = $this->post('/forgot-password', ['email' => 'victim@example.test'])->getStatusCode();
    }

    // The first attempts are accepted (302 back), then the limiter kicks in (429).
    expect($statuses)->toContain(302)
        ->and($statuses[6])->toBe(429);
});

test('the registration endpoint is throttled', function () {
    $statuses = [];

    for ($i = 0; $i < 7; $i++) {
        $statuses[] = $this->post('/register', ['email' => 'spam@example.test'])->getStatusCode();
    }

    expect($statuses[6])->toBe(429);
});

test('the authenticated API carries the api throttle', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/user')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60);
});

test('the MCP endpoint is throttled', function () {
    // Mcp::web registers GET/POST/DELETE for the same URI; the JSON-RPC calls go
    // through POST, which is the one that must be throttled.
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'mcp/board' && in_array('POST', $r->methods(), true));

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:mcp');
});
