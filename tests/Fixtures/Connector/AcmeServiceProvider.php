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
        // (e.g. Shelf) registers its page the same way. The name MUST go
        // through the fluent registrar (before get): on a host with cached
        // routes the compiled collection never indexes a late ->name() and
        // refreshNameLookups() is a no-op there.
        Route::middleware(['web', 'auth'])
            ->name('acme-board.show')
            ->get('/acme-board/{board}', fn (Board $board) => 'acme-board:'.$board->id);
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
