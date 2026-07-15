<?php

namespace App\Livewire\Boards;

use App\Events\BoardActivity;
use App\Models\BoardList;
use App\Plugins\PluginEngine;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Renders one plugin-sourced list's read-only items. Loaded lazily so the board
 * paints immediately and the (potentially slow, network-bound) plugin fetch
 * happens after — showing an animated skeleton meanwhile. Kept separate from
 * the drag-and-drop lists in Show (which must stay in one component for
 * cross-list wire:sort).
 */
#[Lazy]
class PluginList extends Component
{
    public BoardList $list;

    /** Current page size, grown by infinite scroll (loadMore). */
    public int $limit = PluginEngine::DEFAULT_LIMIT;

    public function mount(BoardList $list): void
    {
        Gate::authorize('view', $list->board);

        $this->list = $list;
    }

    /**
     * Grow the window by one page — the plugin refetches with the higher limit.
     */
    public function loadMore(): void
    {
        $this->limit += PluginEngine::DEFAULT_LIMIT;
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:board.{$this->list->board_id},.board.activity" => 'onBoardActivity',
        ];
    }

    /**
     * Only a plugin refresh (manual / webhook / schedule) changes this list's
     * items. Ordinary card activity must NOT re-render here — otherwise every
     * card action would refetch the external API on every connected client
     * (a request storm that starves PHP-FPM workers).
     *
     * @param  array<string, mixed>  $event  the BoardActivity payload
     */
    public function onBoardActivity(array $event = []): void
    {
        if (($event['action'] ?? null) !== 'plugin.refreshed') {
            $this->skipRender();
        }
    }

    /**
     * Bust the cache, refetch and let other viewers update live.
     */
    public function refresh(): void
    {
        Gate::authorize('view', $this->list->board);

        app(PluginEngine::class)->refresh($this->list, $this->limit);

        broadcast(new BoardActivity($this->list->board_id, 'plugin.refreshed', Auth::id()))->toOthers();
        $this->dispatch('toast', message: __('Liste actualisée'), type: 'success');
    }

    public function placeholder(): View
    {
        return view('livewire.boards.partials.plugin-list-skeleton');
    }

    public function render(): View
    {
        $engine = app(PluginEngine::class);
        $items = $engine->listItems($this->list, $this->limit);

        return view('livewire.boards.plugin-list', [
            'items' => $items,
            // Cache still cold → a background warm is in flight; poll until ready.
            'warming' => $engine->isWarming($this->list, $this->limit),
            // A full page suggests there may be more to fetch.
            'hasMore' => $items->count() >= $this->limit,
            'plugin' => app(PluginRegistry::class)->get(optional($this->list->sourcePlugin)->plugin_key),
        ]);
    }
}
