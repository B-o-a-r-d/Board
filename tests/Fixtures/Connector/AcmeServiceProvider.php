<?php

namespace Tests\Fixtures\Connector;

use App\Models\Workspace;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Registers the neutral Acme connector into the host's PluginRegistry. Booted
 * for the whole test suite from {@see TestCase} so plugin-system tests
 * have a concrete, capability-complete Power-Up to exercise.
 */
class AcmeServiceProvider extends PluginServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // The named route backing the fixture's workspace type — a real plugin
        // (e.g. Shelf) registers its page the same way.
        Route::middleware(['web', 'auth'])
            ->get('/acme-space/{workspace}', fn (Workspace $workspace) => 'acme-space:'.$workspace->id)
            ->name('acme-space.show');

        // Registered after boot (runtime plugin): the name lookup table must be
        // refreshed or Route::has() cannot see the new name.
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    protected function plugin(): Plugin
    {
        return new AcmePlugin;
    }

    protected function translationsPath(): ?string
    {
        return null;
    }
}
