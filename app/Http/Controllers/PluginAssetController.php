<?php

namespace App\Http\Controllers;

use App\Plugins\PluginAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a plugin's pre-built front-end asset (declared via ProvidesAssets)
 * straight from the install volume — no copy to public/ (which resets on
 * redeploy). Only files the plugin actually declared are served (whitelist),
 * with an immutable far-future cache since the URL carries a content hash.
 */
class PluginAssetController extends Controller
{
    private const MIMES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
    ];

    public function __invoke(Request $request, PluginAssets $assets, string $plugin, string $file): BinaryFileResponse
    {
        // Reject anything but a bare filename before touching the registry.
        abort_unless($file === basename($file), 404);

        $path = $assets->path($plugin, $file);

        abort_if($path === null, 404);

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        abort_unless(isset(self::MIMES[$extension]), 404);

        return response()->file($path, [
            'Content-Type' => self::MIMES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
