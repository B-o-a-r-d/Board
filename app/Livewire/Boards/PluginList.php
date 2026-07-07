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
        // Re-read the (cache-warmed) items when anyone broadcasts board activity.
        return [
            "echo-private:board.{$this->list->board_id},.board.activity" => '$refresh',
        ];
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
        $items = app(PluginEngine::class)->listItems($this->list, $this->limit);

        return view('livewire.boards.plugin-list', [
            'items' => $items,
            // A full page suggests there may be more to fetch.
            'hasMore' => $items->count() >= $this->limit,
            'plugin' => app(PluginRegistry::class)->get(optional($this->list->sourcePlugin)->plugin_key),
        ]);
    }
}
