<?php

namespace App\Plugins;

use App\Models\Board;
use Board\PluginSdk\Contracts\PluginContext as PluginContextContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Host implementation of the SDK's PluginContext — lets decoupled plugin code
 * (e.g. MCP tools) read installed-instance config and check board access
 * without depending on the app's models.
 */
class PluginContext implements PluginContextContract
{
    public function boardPluginConfig(string $boardPublicId, string $pluginKey): ?array
    {
        if (! $this->userCanAccessBoard($boardPublicId)) {
            return null;
        }

        $board = Board::where('public_id', $boardPublicId)->first();

        return $board?->plugins()
            ->where('plugin_key', $pluginKey)
            ->where('is_active', true)
            ->first()
            ?->config;
    }

    public function userCanAccessBoard(string $boardPublicId): bool
    {
        $board = Board::where('public_id', $boardPublicId)->first();

        return $board !== null && Auth::check() && Gate::allows('view', $board);
    }
}
