<?php

namespace Tests\Fixtures\Connector;

use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\PluginServiceProvider;
use Tests\TestCase;

/**
 * Registers the neutral Acme connector into the host's PluginRegistry. Booted
 * for the whole test suite from {@see TestCase} so plugin-system tests
 * have a concrete, capability-complete Power-Up to exercise.
 */
class AcmeServiceProvider extends PluginServiceProvider
{
    protected function plugin(): Plugin
    {
        return new AcmePlugin;
    }

    protected function translationsPath(): ?string
    {
        return null;
    }
}
