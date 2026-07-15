<?php

namespace App\Livewire\Boards\Concerns;

use App\Automations\AutomationEngine;
use App\Events\BoardActivity;
use App\Livewire\Boards\ListColumn;
use App\Livewire\Boards\Show;
use App\Models\Activity;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Card mutations + their shared helpers, used by both the board grid columns
 * ({@see ListColumn}) and the parent board component
 * ({@see Show}, whose table/calendar views still move
 * cards). Keeping a single copy avoids the two drifting apart.
 *
 * Cross-column effects dispatch a `cards:refresh` event (optionally scoped to a
 * list id) so the affected ListColumn(s) re-sync; in the Show/table context that
 * event simply has no listeners. Requires the consumer to expose `$board`,
 * `$newCardTitle` (array keyed by list id) and the card filter properties.
 */
trait InteractsWithBoardCards
{
    public function toggleCardComplete(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->board->cards()->findOrFail($cardId);
        $card->update(['completed_at' => $card->completed_at ? null : now()]);

        $type = $card->completed_at ? 'card.completed' : 'card.uncompleted';
        $this->logActivity($type, $card->id, ['card_title' => $card->title]);
        $this->broadcastActivity($type, [$card->board_list_id]);

        if ($card->completed_at && app(AutomationEngine::class)->fire('card.completed', $card->fresh()) > 0) {
            // An automation may have moved the card elsewhere — re-sync all columns.
            $this->dispatch('cards:refresh');
        }
    }

    public function addCard(int $listId): void
    {
        $this->authorizeContribution();

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

        $this->logActivity('card.created', $card->id, ['card_title' => $card->title, 'list' => $list->name]);
        app(AutomationEngine::class)->fire('card.created', $card, ['list_id' => $list->id]);

        $this->newCardTitle[$listId] = '';
        $this->broadcastActivity('card.created', [$list->id]);

        // Open the new card's detail modal so the user can fill it in right away.
        $this->dispatch('open-card', cardId: $card->id);
    }

    public function addCardFromTemplate(int $listId, int $templateId): void
    {
        $this->authorizeContribution();

        $list = $this->listForBoard($listId);
        $template = CardTemplate::findOrFail($templateId);

        $card = $list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $template->title,
            'description' => $template->description,
            'cover_color' => $template->cover_color,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        foreach ($template->checklists ?? [] as $checklistIndex => $checklist) {
            $newChecklist = $card->checklists()->create([
                'title' => $checklist['title'] ?? 'Checklist',
                'position' => $checklistIndex,
            ]);

            foreach ($checklist['items'] ?? [] as $itemIndex => $content) {
                $newChecklist->items()->create(['content' => $content, 'position' => $itemIndex]);
            }
        }

        $this->logActivity('card.created', $card->id, ['card_title' => $card->title, 'list' => $list->name, 'from_template' => true]);
        app(AutomationEngine::class)->fire('card.created', $card, ['list_id' => $list->id]);
        $this->broadcastActivity('card.created', [$list->id]);
        $this->dispatch('toast', message: __('Carte créée depuis le modèle'), type: 'success');
    }

    public function archiveCard(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $card->update(['archived_at' => now()]);
        $this->logActivity('card.archived', $cardId, ['card_title' => $card->title, 'list' => $card->list?->name]);
        app(AutomationEngine::class)->fire('card.archived', $card);
        $this->broadcastActivity('card.archived', [$card->board_list_id]);
    }

    public function duplicateCard(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->board->cards()->with(['labels', 'members'])->findOrFail($cardId);

        $copy = $card->list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $card->title.' (copie)',
            'description' => $card->description,
            'cover_path' => $card->cover_path,
            'cover_color' => $card->cover_color,
            'due_at' => $card->due_at,
            'position' => (int) $card->list->cards()->max('position') + 1,
        ]);

        $copy->labels()->attach($card->labels->pluck('id'));
        $copy->members()->attach($card->members->pluck('id'));

        $this->logActivity('card.duplicated', $copy->id, ['from' => $card->id]);
        app(AutomationEngine::class)->fire('card.created', $copy, ['list_id' => $copy->board_list_id]);
        $this->broadcastActivity('card.duplicated', [$copy->board_list_id]);
        $this->dispatch('toast', message: __('Carte dupliquée'), type: 'success');
    }

    public function moveCard(int $id, int $position, int $listId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($id);
        $targetList = $this->listForBoard($listId);
        $sourceListId = $card->board_list_id;
        $sourceListName = $card->list?->name;

        $card->board_list_id = $targetList->id;
        $card->save();

        $this->resequence($targetList->id, $id, $position);

        $ranAutomations = 0;

        if ($sourceListId !== $targetList->id) {
            $this->resequence($sourceListId);
            $this->logActivity('card.moved', $card->id, ['card_title' => $card->title, 'from_list' => $sourceListName, 'to_list' => $targetList->name]);

            $ranAutomations = app(AutomationEngine::class)->fire('card.moved', $card->fresh(), [
                'to_list_id' => $targetList->id,
                'from_list_id' => $sourceListId,
            ]);
        }

        $this->broadcastActivity('card.moved', array_values(array_unique([$sourceListId, $targetList->id])));

        // Optimistic UI: SortableJS already placed the card — even across columns —
        // so skip the actor's re-render. Only when an automation mutated the card
        // do we re-sync every column.
        if ($ranAutomations > 0) {
            $this->dispatch('cards:refresh');
        } else {
            $this->skipRender();
        }
    }

    public function moveCardToList(int $cardId, int $listId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $targetList = $this->listForBoard($listId);
        $sourceListId = $card->board_list_id;
        $sourceListName = $card->list?->name;

        if ($sourceListId === $targetList->id) {
            return;
        }

        $card->board_list_id = $targetList->id;
        $card->position = (int) $targetList->cards()->max('position') + 1;
        $card->save();

        $this->resequence($targetList->id);
        $this->resequence($sourceListId);

        $this->logActivity('card.moved', $card->id, ['card_title' => $card->title, 'from_list' => $sourceListName, 'to_list' => $targetList->name]);
        app(AutomationEngine::class)->fire('card.moved', $card->fresh(), [
            'to_list_id' => $targetList->id,
            'from_list_id' => $sourceListId,
        ]);

        $this->broadcastActivity('card.moved', [$sourceListId, $targetList->id]);

        // The target column needs to show the card; this (source) column re-renders.
        $this->dispatch('cards:refresh', listId: $targetList->id);
    }

    // --- Shared helpers -------------------------------------------------------

    private function authorizeContribution(): void
    {
        $this->authorize('contribute', $this->board);
    }

    private function cardForBoard(int $cardId): Card
    {
        return $this->board->cards()->findOrFail($cardId);
    }

    private function listForBoard(int $listId): BoardList
    {
        return $this->board->lists()->findOrFail($listId);
    }

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

    /**
     * @param  array<int, int>  $listIds  lists whose cards changed, so remote
     *                                    ListColumns can refresh only when affected
     *                                    (empty = board-level → every column refreshes).
     */
    private function broadcastActivity(string $action, array $listIds = []): void
    {
        broadcast(new BoardActivity($this->board->id, $action, Auth::id(), $listIds))->toOthers();
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

    /**
     * A phantom card carrying the board + list context, so board-scope automation
     * actions can run against a list without a real card.
     */
    private function phantomListCard(int $listId): Card
    {
        $card = new Card(['board_id' => $this->board->id, 'board_list_id' => $listId]);
        $card->setRelation('board', $this->board);

        return $card;
    }

    /**
     * @param  Builder<Card>  $query
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

        if ($this->filterLabels !== []) {
            $query->whereHas('labels', fn ($labels) => $labels->whereIn('labels.id', $this->filterLabels));
        }

        if ($this->filterUnassigned) {
            $query->whereDoesntHave('members');
        } elseif ($this->filterMembers !== []) {
            $query->whereHas('members', fn ($members) => $members->whereIn('users.id', $this->filterMembers));
        }

        match ($this->filterDue) {
            'overdue' => $query->whereNotNull('due_at')->whereNull('completed_at')->where('due_at', '<', now()),
            'due' => $query->whereNotNull('due_at'),
            'none' => $query->whereNull('due_at'),
            default => null,
        };
    }
}
