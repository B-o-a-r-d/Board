<?php

namespace Acme\DemoPlugin;

use Board\PluginSdk\Contracts\Plugin;

/**
 * Minimal SDK-only plugin used as a test fixture for the runtime marketplace
 * loader/installer. Not autoloaded by Composer — it is required at runtime by
 * PluginLoaderServiceProvider from the extracted package.
 */
class DemoPlugin implements Plugin
{
    public static function key(): string
    {
        return 'demo';
    }

    public function label(): string
    {
        return 'Demo';
    }

    public function description(): string
    {
        return 'A demo plugin.';
    }

    public function icon(): string
    {
        return 'sparkle';
    }

    public function requiresOAuth(): bool
    {
        return false;
    }

    public function oauthProvider(): ?string
    {
        return null;
    }

    public function configFields(array $config = []): array
    {
        return [];
    }
}
