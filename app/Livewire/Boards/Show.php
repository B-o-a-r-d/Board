<?php

namespace App\Livewire\Boards;

use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Board $board;

    public string $newListName = '';

    /** @var array<int, string> */
    public array $newCardTitle = [];

    public string $search = '';

    public ?int $filterLabel = null;

    public ?int $filterMember = null;

    public string $filterDue = '';

    public bool $showTrash = false;

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
    }

    public function mount(Board $board): void
    {
        $this->authorize('view', $board);

        $this->board = $board;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterLabel', 'filterMember', 'filterDue');
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterLabel !== null || $this->filterMember !== null || $this->filterDue !== '';
    }

    public function deleteBoard(): mixed
    {
        $this->authorize('delete', $this->board);

        $this->board->delete();

        return $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:board.{$this->board->id},.board.activity" => 'onBoardActivity',
            'board-refresh' => 'onBoardActivity',
        ];
    }

    /**
     * Both remote broadcasts and local card edits simply trigger a re-render,
     * which re-queries fresh data.
     */
    public function onBoardActivity(): void {}

    public function addList(): void
    {
        $this->authorize('view', $this->board);

        $data = $this->validate([
            'newListName' => ['required', 'string', 'max:255'],
        ]);

        $this->board->lists()->create([
            'name' => $data['newListName'],
            'position' => (int) $this->board->lists()->max('position') + 1,
        ]);

        $this->newListName = '';
        $this->broadcastActivity('list.created');
    }

    public function renameList(int $listId, string $name): void
    {
        $this->authorize('view', $this->board);

        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->listForBoard($listId)->update(['name' => $name]);
        $this->broadcastActivity('list.renamed');
    }

    public function archiveList(int $listId): void
    {
        $this->authorize('view', $this->board);

        $this->listForBoard($listId)->update(['archived_at' => now()]);
        $this->broadcastActivity('list.archived');
    }

    public function restoreList(int $listId): void
    {
        $this->authorize('view', $this->board);

        $this->board->lists()->whereKey($listId)->update(['archived_at' => null]);
        $this->broadcastActivity('list.restored');
    }

    public function deleteListPermanently(int $listId): void
    {
        $this->authorize('view', $this->board);

        $this->board->lists()->whereKey($listId)->delete();
        $this->broadcastActivity('list.deleted');
    }

    public function reorderLists(int $id, int $position): void
    {
        $this->authorize('view', $this->board);

        $ids = $this->board->lists()
            ->where('id', '!=', $id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$id]);

        foreach ($ids as $index => $listId) {
            BoardList::whereKey($listId)->update(['position' => $index]);
        }

        $this->broadcastActivity('list.reordered');
    }

    public function addCard(int $listId): void
    {
        $this->authorize('view', $this->board);

        $title = trim($this->newCardTitle[$listId] ?? '');

        if ($title === '') {
            return;
        }

        $list = $this->listForBoard($listId);

        $card = $list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $title,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $this->logActivity('card.created', $card->id, ['title' => $card->title]);

        $this->newCardTitle[$listId] = '';
        $this->broadcastActivity('card.created');
    }

    public function archiveCard(int $cardId): void
    {
        $this->authorize('view', $this->board);

        $this->cardForBoard($cardId)->update(['archived_at' => now()]);
        $this->logActivity('card.archived', $cardId);
        $this->broadcastActivity('card.archived');
    }

    public function restoreCard(int $cardId): void
    {
        $this->authorize('view', $this->board);

        $this->board->cards()->whereKey($cardId)->update(['archived_at' => null]);
        $this->broadcastActivity('card.restored');
    }

    public function deleteCardPermanently(int $cardId): void
    {
        $this->authorize('view', $this->board);

        $this->board->cards()->whereKey($cardId)->delete();
        $this->broadcastActivity('card.deleted');
    }

    public function moveCard(int $id, int $position, int $listId): void
    {
        $this->authorize('view', $this->board);

        $card = $this->cardForBoard($id);
        $targetList = $this->listForBoard($listId);
        $sourceListId = $card->board_list_id;

        $card->board_list_id = $targetList->id;
        $card->save();

        $this->resequence($targetList->id, $id, $position);

        if ($sourceListId !== $targetList->id) {
            $this->resequence($sourceListId);
            $this->logActivity('card.moved', $card->id, ['to_list' => $targetList->name]);
        }

        $this->broadcastActivity('card.moved');
    }

    /**
     * Renumber a list's cards, optionally inserting a moved card at a position.
     */
    private function resequence(int $listId, ?int $movedId = null, ?int $position = null): void
    {
        $ids = Card::query()
            ->where('board_list_id', $listId)
            ->when($movedId !== null, fn ($query) => $query->where('id', '!=', $movedId))
            ->orderBy('position')
            ->pluck('id')
            ->all();

        if ($movedId !== null && $position !== null) {
            $position = max(0, min($position, count($ids)));
            array_splice($ids, $position, 0, [$movedId]);
        }

        foreach ($ids as $index => $cardId) {
            Card::whereKey($cardId)->update(['position' => $index]);
        }
    }

    private function listForBoard(int $listId): BoardList
    {
        return $this->board->lists()->findOrFail($listId);
    }

    private function cardForBoard(int $cardId): Card
    {
        return $this->board->cards()->findOrFail($cardId);
    }

    private function broadcastActivity(string $action): void
    {
        broadcast(new BoardActivity($this->board->id, $action, Auth::id()))->toOthers();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(string $type, ?int $cardId = null, array $properties = []): void
    {
        Activity::create([
            'board_id' => $this->board->id,
            'card_id' => $cardId,
            'user_id' => Auth::id(),
            'type' => $type,
            'properties' => $properties,
        ]);
    }

    public function render(): View
    {
        $lists = $this->board->lists()
            ->whereNull('archived_at')
            ->with([
                'cards' => function ($query) {
                    $query->whereNull('archived_at')->orderBy('position')->withCount('attachments');
                    $this->applyCardFilters($query);
                },
                'cards.members',
                'cards.labels',
                'cards.checklists.items',
            ])
            ->orderBy('position')
            ->get();

        return view('livewire.boards.show', [
            'lists' => $lists,
            'labels' => $this->board->labels,
            'members' => $this->board->members,
            'archivedLists' => $this->showTrash ? $this->board->lists()->whereNotNull('archived_at')->orderBy('name')->get() : collect(),
            'archivedCards' => $this->showTrash ? $this->board->cards()->whereNotNull('archived_at')->with('list')->latest('archived_at')->get() : collect(),
        ]);
    }

    /**
     * Apply the board's card filters (text, label, member, due state).
     *
     * @param  HasMany<Card, BoardList>  $query
     */
    private function applyCardFilters($query): void
    {
        if ($this->search !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($scoped) use ($term) {
                $scoped->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        if ($this->filterLabel !== null) {
            $query->whereHas('labels', fn ($labels) => $labels->whereKey($this->filterLabel));
        }

        if ($this->filterMember !== null) {
            $query->whereHas('members', fn ($members) => $members->whereKey($this->filterMember));
        }

        match ($this->filterDue) {
            'overdue' => $query->whereNotNull('due_at')->whereNull('completed_at')->where('due_at', '<', now()),
            'due' => $query->whereNotNull('due_at'),
            'none' => $query->whereNull('due_at'),
            default => null,
        };
    }
}
