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
            // Another user changed the board → re-sync this column.
            "echo-private:board.{$this->board->id},.board.activity" => '$refresh',
            // A cross-column action (card left/entered a list, or a list-menu bulk
            // action on Show) asks the affected columns to refresh.
            'cards:refresh' => 'refreshIfAffected',
        ];
    }

    public function refreshIfAffected(?int $listId = null): void
    {
        // A null id targets every column; otherwise only the named column refreshes.
        if ($listId !== null && $listId !== $this->list->id) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        $cards = $this->list->cards()
            ->whereNull('archived_at')
            ->orderBy('position')
            ->withCount('attachments')
            ->with(['members', 'labels', 'checklists.items', 'customFieldValues']);

        $this->applyCardFilters($cards);

        return view('livewire.boards.list-column', [
            'cards' => $cards->get(),
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
