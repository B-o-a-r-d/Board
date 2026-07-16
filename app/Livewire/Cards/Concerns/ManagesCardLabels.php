<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use App\Models\Label;
use Illuminate\Support\Facades\Validator;

/**
 * Labels on the open card + board label CRUD from the modal.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardLabels
{
    public string $newLabelName = '';

    public string $newLabelColor = '#3b82f6';

    public function toggleLabel(int $labelId): void
    {
        $card = $this->guardedCard();
        $label = $this->board->labels()->findOrFail($labelId);

        $result = $card->labels()->toggle($label->id);

        app(AutomationEngine::class)->fire(
            in_array($label->id, $result['attached'], true) ? 'card.label_added' : 'card.label_removed',
            $card,
            ['label_id' => $label->id],
        );

        $this->touched('card.labels');
    }

    public function createLabel(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'newLabelName' => ['nullable', 'string', 'max:255'],
            'newLabelColor' => ['required', 'string', Label::COLOR_RULE],
        ]);

        $label = $this->board->labels()->create([
            'name' => $data['newLabelName'] ?: null,
            'color' => $data['newLabelColor'],
        ]);

        $card->labels()->attach($label->id);
        $this->reset('newLabelName');
        $this->touched('label.created');
        // New label definition → Show's filter dropdown must pick it up.
        $this->dispatch('board-refresh');
    }

    public function renameLabel(int $labelId, string $name): void
    {
        $this->authorize('contribute', $this->board);

        $this->board->labels()->findOrFail($labelId)->update(['name' => trim($name) ?: null]);
        $this->touched('label.renamed');
        $this->labelDefinitionChanged();
    }

    public function recolorLabel(int $labelId, string $color): void
    {
        $this->authorize('contribute', $this->board);

        Validator::make(['color' => $color], ['color' => ['required', 'string', Label::COLOR_RULE]])->validate();

        $this->board->labels()->whereKey($labelId)->update(['color' => $color]);
        $this->touched('label.recolored');
        $this->labelDefinitionChanged();
    }

    public function deleteLabel(int $labelId): void
    {
        $this->authorize('contribute', $this->board);

        $this->board->labels()->whereKey($labelId)->delete();
        $this->touched('label.deleted');
        $this->labelDefinitionChanged();
    }

    /**
     * A label definition changed: its chips may sit on cards in every column,
     * and Show's filter dropdown lists it — refresh both (touched() only
     * covered this card's own column).
     */
    private function labelDefinitionChanged(): void
    {
        $this->dispatch('cards:refresh');
        $this->dispatch('board-refresh');
    }
}
