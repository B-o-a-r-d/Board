<?php

namespace App\Livewire\Cards\Concerns;

use App\Models\CardLink;

/**
 * Card-to-card links (blocks / blocked by / relates to).
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardLinks
{
    public string $linkType = 'blocks';

    public string $linkSearch = '';

    /**
     * Link another card on the same board. Types are normalised so only
     * 'blocks' and (symmetric) 'relates_to' rows are stored.
     */
    public function linkCard(int $relatedCardId, ?string $type = null): void
    {
        $card = $this->guardedCard();

        $type ??= $this->linkType;

        if (! in_array($type, ['blocks', 'blocked_by', 'relates_to'], true)) {
            return;
        }

        $related = $this->board->cards()->whereKey($relatedCardId)->first();

        if (! $related || $related->id === $card->id) {
            return;
        }

        if ($type === 'relates_to') {
            CardLink::firstOrCreate([
                'card_id' => min($card->id, $related->id),
                'related_card_id' => max($card->id, $related->id),
                'type' => 'relates_to',
            ]);
        } else {
            [$from, $to] = $type === 'blocks' ? [$card->id, $related->id] : [$related->id, $card->id];
            CardLink::firstOrCreate(['card_id' => $from, 'related_card_id' => $to, 'type' => 'blocks']);
        }

        $this->linkSearch = '';
        $this->touched('card.linked');
    }

    public function unlinkCard(int $linkId): void
    {
        $card = $this->guardedCard();

        CardLink::whereKey($linkId)
            ->where(fn ($q) => $q->where('card_id', $card->id)->orWhere('related_card_id', $card->id))
            ->delete();

        $this->touched('card.unlinked');
    }
}
