<?php

namespace App\Livewire\Boards;

use App\Livewire\Boards\Concerns\InteractsWithBoardCards;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\CardTemplate;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * One board list's CARDS (the <ul>, add-card form and per-card actions).
 *
 * Extracted from the monolithic {@see Show} so a card mutation re-renders only
 * its own column instead of the whole board (~30 KB / small morph instead of
 * the full ~1 MB board). The list header/menu stays on Show; card drag stays
 * optimistic (skipRender), exactly as before. Card logic is shared with Show
 * (which still needs it for the table view) via {@see InteractsWithBoardCards}.
 */
class ListColumn extends Component
{
    use InteractsWithBoardCards;

    public Board $board;

    public BoardList $list;

    /** @var array<int, string> New-card title, keyed by list id (shared trait shape). */
    public array $newCardTitle = [];

    /** How many cards this column currently renders (grown by {@see loadMore}). */
    public int $visibleCards = self::INITIAL_CARDS;

    /** First paint stays short so even a heavy list appears instantly. */
    private const INITIAL_CARDS = 12;

    /** Each scroll / "load more" reveals another chunk. */
    private const PAGE_SIZE = 25;

    /** Card filters owned by the parent Show; re-render this column when they change. */
    #[Reactive]
    public string $search = '';

    /** @var array<int, int> */
    #[Reactive]
    public array $filterLabels = [];

    /** @var array<int, int> */
    #[Reactive]
    public array $filterMembers = [];

    #[Reactive]
    public bool $filterUnassigned = false;

    #[Reactive]
    public string $filterDue = '';

    #[Reactive]
    public bool $canContribute = false;

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            // Another user changed the board → refresh only if this list is affected.
            "echo-private:board.{$this->board->id},.board.activity" => 'onRemoteActivity',
            // A cross-column action (card left/entered a list, or a list-menu bulk
            // action on Show) asks the affected columns to refresh.
            'cards:refresh' => 'refreshIfAffected',
        ];
    }

    /**
     * @param  array<string, mixed>  $event  the BoardActivity payload
     */
    public function onRemoteActivity(array $event = []): void
    {
        $listIds = $event['listIds'] ?? [];

        // Empty listIds = a board-level change → refresh. Otherwise refresh only
        // when this column's list is among the ones that changed. (Falls back to a
        // full refresh if the payload is ever absent — no worse than before.)
        if (is_array($listIds) && $listIds !== [] && ! in_array($this->list->id, $listIds, true)) {
            $this->skipRender();
        }
    }

    public function refreshIfAffected(?int $listId = null): void
    {
        // A null id targets every column; otherwise only the named column refreshes.
        if ($listId !== null && $listId !== $this->list->id) {
            $this->skipRender();
        }
    }

    /** Skeleton shown while the column lazy-loads its cards (progressive paint). */
    public function placeholder(): View
    {
        return view('livewire.boards.list-column-placeholder');
    }

    /** Reveal the next page of cards (infinite scroll / "load more" on heavy lists). */
    public function loadMore(): void
    {
        $this->visibleCards += self::PAGE_SIZE;
    }

    public function render(): View
    {
        // A heavy list only paints its first page; the rest loads on scroll so a
        // 300-card column never renders 300 partials at once.
        $query = $this->list->cards()->whereNull('archived_at');
        $this->applyCardFilters($query);

        $total = (clone $query)->count();

        $cards = $query
            ->orderBy('position')
            ->withCount('attachments')
            ->with(['members', 'labels', 'checklists.items', 'customFieldValues'])
            ->limit($this->visibleCards)
            ->get();

        return view('livewire.boards.list-column', [
            'cards' => $cards,
            // True (filtered) total — the DOM only holds the paginated page, so
            // the list header reads this instead of counting <li> nodes.
            'totalCards' => $total,
            'hasMore' => $total > $cards->count(),
            'remaining' => max(0, $total - $cards->count()),
            'mirrors' => $this->list->mirrors()->with([
                'card' => fn ($q) => $q->whereNull('archived_at')->withCount('attachments'),
                'card.members', 'card.labels', 'card.checklists.items', 'card.board:id,name,public_id',
            ])->orderBy('position')->get(),
            'lists' => $this->board->lists()->whereNull('archived_at')->orderBy('position')->get(['id', 'name']),
            'customFields' => $this->board->customFields()->visibleOn($this->board)->orderBy('position')->get(),
            'members' => $this->board->members()->orderBy('name')->get(),
            'cardTemplates' => CardTemplate::orderBy('name')->get(),
        ]);
    }
}
