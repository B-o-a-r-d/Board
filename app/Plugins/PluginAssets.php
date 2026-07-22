<?php

namespace App\Plugins;

use App\Http\Controllers\PluginAssetController;
use Board\PluginSdk\Contracts\AssetRegistrar;
use Board\PluginSdk\Contracts\ProvidesAssets;

/**
 * Registry of plugin front-end assets ({@see ProvidesAssets}).
 * Plugin service providers feed it at boot (through the SDK's AssetRegistrar
 * binding); {@see PluginAssetController} serves the files
 * and the `<x-plugin-assets>` component injects them on a plugin's pages.
 *
 * Files live in the package's `dist/` directory on the install volume — never
 * copied to public/ (which resets on redeploy) — and are addressed by a
 * content hash so the browser caches them immutably until the plugin updates.
 */
class PluginAssets implements AssetRegistrar
{
    /** @var array<string, array{dir: string, styles: array<int, string>, scripts: array<int, string>}> */
    private array $plugins = [];

    public function register(string $key, string $baseDir, array $styles, array $scripts): void
    {
        $this->plugins[$key] = [
            'dir' => rtrim($baseDir, '/').'/dist',
            'styles' => array_values($styles),
            'scripts' => array_values($scripts),
        ];
    }

    /**
     * @return array{dir: string, styles: array<int, string>, scripts: array<int, string>}|null
     */
    public function for(string $key): ?array
    {
        return $this->plugins[$key] ?? null;
    }

    /**
     * Whether $file is a declared asset of $key — the whitelist the controller
     * checks before serving anything from disk.
     */
    public function has(string $key, string $file): bool
    {
        $plugin = $this->plugins[$key] ?? null;

        return $plugin !== null && in_array($file, [...$plugin['styles'], ...$plugin['scripts']], true);
    }

    /**
     * Absolute path of a declared asset file, or null when it is not declared
     * or missing on disk.
     */
    public function path(string $key, string $file): ?string
    {
        if (! $this->has($key, $file)) {
            return null;
        }

        $path = $this->plugins[$key]['dir'].'/'.$file;

        return is_file($path) ? $path : null;
    }

    /**
     * Short content hash of an asset, for immutable cache-busting URLs. Falls
     * back to '0' when the file is absent (the URL still resolves to a 404).
     */
    public function version(string $key, string $file): string
    {
        $path = $this->path($key, $file);

        return $path === null ? '0' : substr((string) hash_file('crc32b', $path), 0, 8);
    }
}
