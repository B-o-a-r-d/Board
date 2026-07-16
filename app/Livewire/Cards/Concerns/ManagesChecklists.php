<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use App\Models\Card;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Checklists and checklist items of the open card.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesChecklists
{
    public string $newChecklistTitle = '';

    /** @var array<int, string> */
    public array $newChecklistItem = [];

    public function addChecklist(): void
    {
        $card = $this->guardedCard();

        $this->validate(['newChecklistTitle' => ['required', 'string', 'max:255']]);

        $card->checklists()->create([
            'title' => $this->newChecklistTitle,
            'position' => (int) $card->checklists()->max('position') + 1,
        ]);

        $this->reset('newChecklistTitle');
        $this->logActivity($card, 'checklist.created');
        app(AutomationEngine::class)->fire('checklist.added', $card);
        $this->touched('checklist.created');
    }

    public function deleteChecklist(int $checklistId): void
    {
        $card = $this->guardedCard();
        $card->checklists()->findOrFail($checklistId)->delete();
        $this->logActivity($card, 'checklist.deleted');
        $this->touched('checklist.deleted');
    }

    public function addChecklistItem(int $checklistId): void
    {
        $card = $this->guardedCard();
        $checklist = $card->checklists()->findOrFail($checklistId);

        $content = trim($this->newChecklistItem[$checklistId] ?? '');

        if ($content === '') {
            return;
        }

        $checklist->items()->create([
            'content' => $content,
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        $this->newChecklistItem[$checklistId] = '';
        $this->touched('checklist.item.added');
    }

    public function toggleChecklistItem(int $itemId): void
    {
        $card = $this->guardedCard();

        $item = ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId);

        $item->update(['is_completed' => ! $item->is_completed]);

        if ($item->is_completed) {
            $engine = app(AutomationEngine::class);
            $engine->fire('checklist.item_checked', $card, ['item_id' => $item->id]);

            // Last unchecked item just got ticked → the whole checklist is done.
            if ($item->checklist->items()->where('is_completed', false)->doesntExist()) {
                $engine->fire('checklist.completed', $card, ['checklist_id' => $item->checklist_id]);
            }
        }

        $this->touched('checklist.item.toggled');
    }

    public function deleteChecklistItem(int $itemId): void
    {
        $card = $this->guardedCard();

        ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId)
            ->delete();

        $this->touched('checklist.item.deleted');
    }

    /**
     * Assign (or clear, with null) a board member to a checklist item — turning
     * items into real sub-tasks.
     */
    public function assignChecklistItem(int $itemId, ?int $userId): void
    {
        $card = $this->guardedCard();
        $item = $this->guardedChecklistItem($card, $itemId);

        if ($userId !== null && ! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $item->update(['assigned_to' => $userId]);
        $this->touched('checklist.item.assigned');
    }

    /**
     * Set (or clear, with an empty value) a checklist item due date (noon).
     */
    public function setChecklistItemDue(int $itemId, ?string $date): void
    {
        $card = $this->guardedCard();
        $item = $this->guardedChecklistItem($card, $itemId);

        $item->update([
            'due_at' => ($date !== null && $date !== '') ? Carbon::parse($date)->setTime(12, 0) : null,
        ]);
        $this->touched('checklist.item.due');
    }

    private function guardedChecklistItem(Card $card, int $itemId): ChecklistItem
    {
        return ChecklistItem::query()
            ->whereHas('checklist', fn ($query) => $query->where('card_id', $card->id))
            ->findOrFail($itemId);
    }
}
