<?php

use App\Models\User;
use App\Plugins\PluginAssets;

/**
 * Plugin front-end assets (SDK ProvidesAssets): the Acme fixture ships
 * tests/Fixtures/dist/acme.{css,js} and declares them, so the host registers,
 * serves and injects them.
 */
test('a ProvidesAssets plugin registers its dist files with the host', function () {
    $bundle = app(PluginAssets::class)->for('acme');

    expect($bundle)->not->toBeNull()
        ->and($bundle['styles'])->toBe(['acme.css'])
        ->and($bundle['scripts'])->toBe(['acme.js'])
        ->and($bundle['dir'])->toEndWith('tests/Fixtures/dist');
});

test('the asset route serves a declared file with an immutable cache and correct type', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('plugins.asset', ['plugin' => 'acme', 'file' => 'acme.css']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/css')
        ->and($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('the asset route refuses a file the plugin did not declare', function () {
    $this->actingAs(User::factory()->create());

    // Present on disk but not declared, and a path-traversal attempt.
    $this->get(route('plugins.asset', ['plugin' => 'acme', 'file' => 'secret.env']))->assertNotFound();
    $this->get('/plugins/acme/assets/'.rawurlencode('../composer.json'))->assertNotFound();
});

test('the asset route requires authentication', function () {
    $this->get(route('plugins.asset', ['plugin' => 'acme', 'file' => 'acme.css']))->assertRedirect(route('login'));
});

test('x-plugin-assets emits the plugin link and script directly with hashed, navigate-tracked urls', function () {
    $html = view('components.plugin-assets', ['plugin' => 'acme'])->render();

    expect($html)->toContain('/plugins/acme/assets/acme.css?v=')
        ->and($html)->toContain('/plugins/acme/assets/acme.js?v=')
        ->and($html)->toContain('<link rel="stylesheet" data-navigate-track="reload"')
        ->and($html)->toContain('<script data-navigate-track="reload"');
});

test('x-plugin-assets is a no-op for a plugin without assets', function () {
    $html = view('components.plugin-assets', ['plugin' => 'unknown-plugin'])->render();

    expect(trim($html))->toBe('');
});
