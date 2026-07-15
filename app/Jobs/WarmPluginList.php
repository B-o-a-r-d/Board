<?php

namespace App\Jobs;

use App\Models\BoardList;
use App\Plugins\PluginEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fetches a plugin list's items (a slow, network-bound call) off the web request,
 * so opening a board never blocks on a plugin's external API. The result is
 * cached by {@see PluginEngine::warm}; the PluginList component polls until the
 * cache is warm, then renders it. On the sync queue (tests) this runs inline.
 */
class WarmPluginList implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $listId, public int $limit) {}

    public function handle(PluginEngine $engine): void
    {
        $list = BoardList::find($this->listId);

        if ($list === null || ! $list->isPluginList()) {
            return;
        }

        $engine->warm($list, $this->limit);
    }
}
