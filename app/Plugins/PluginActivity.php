<?php

namespace App\Plugins;

use App\Models\Activity;
use App\Models\BoardPlugin;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

/**
 * Writes a plugin-originated activity into the shared `activities` table,
 * tagged `source = "plugin:<key>"` so the slide-over can group it under the
 * plugin's own tab and delegate its wording to the plugin.
 */
class PluginActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(BoardPlugin $instance, ?Card $card, string $type, array $properties = []): Activity
    {
        return Activity::create([
            'board_id' => $instance->board_id,
            'card_id' => $card?->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'source' => 'plugin:'.$instance->plugin_key,
            'properties' => $properties,
        ]);
    }
}
