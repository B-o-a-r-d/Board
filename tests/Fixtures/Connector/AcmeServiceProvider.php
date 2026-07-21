<?php

namespace Tests\Fixtures\Connector;

use App\Models\Board;
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

        // The named route backing the fixture's board type — a real plugin
        // (e.g. Shelf) registers its page the same way.
        Route::middleware(['web', 'auth'])
            ->get('/acme-board/{board}', fn (Board $board) => 'acme-board:'.$board->id)
            ->name('acme-board.show');

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
