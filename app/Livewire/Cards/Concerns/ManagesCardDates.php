<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use Illuminate\Support\Carbon;

/**
 * Start/due dates of the open card (edit, combine date+time, clear).
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardDates
{
    public ?string $startDate = null;

    public ?string $startTime = null;

    public ?string $dueDate = null;

    public ?string $dueTime = null;

    public function saveDates(): void
    {
        $card = $this->guardedCard();

        $this->validate([
            'startDate' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $start = $this->combineDateTime($this->startDate, $this->startTime);
        $newDue = $this->combineDateTime($this->dueDate, $this->dueTime);

        if ($start !== null && $newDue !== null && $newDue->lt($start)) {
            $this->addError('dueDate', __('L’échéance doit être postérieure au début.'));

            return;
        }

        $hadDue = $card->due_at !== null;

        $card->update([
            'start_at' => $start,
            'due_at' => $newDue,
        ]);

        if ($newDue === null && $hadDue) {
            $this->logActivity($card, 'card.due_removed');
        } elseif ($newDue !== null) {
            $this->logActivity($card, $hadDue ? 'card.due_changed' : 'card.due_set', ['value' => $newDue->translatedFormat('d M Y \à H:i')]);
            app(AutomationEngine::class)->fire('card.due_set', $card);
        }

        $this->touched('card.updated');
    }

    /**
     * Combine a date (required) with an optional time into a Carbon instant.
     * The time is optional — a due date with no time defaults to noon so the
     * schedule saves from the date alone.
     */
    private function combineDateTime(?string $date, ?string $time): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date.' '.(empty($time) ? '12:00' : $time));
    }

    public function clearDates(): void
    {
        $card = $this->guardedCard();

        $hadDue = $card->due_at !== null;

        $card->update(['start_at' => null, 'due_at' => null]);
        $this->startDate = null;
        $this->startTime = null;
        $this->dueDate = null;
        $this->dueTime = null;

        if ($hadDue) {
            $this->logActivity($card, 'card.due_removed');
        }

        $this->touched('card.updated');
    }
}
