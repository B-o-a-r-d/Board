<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use App\Models\User;
use App\Notifications\CardNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Assignees and watchers of the open card.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardMembers
{
    /**
     * Toggle whether the current user watches this card (personal subscription:
     * receive comment notifications without being assigned).
     */
    public function toggleWatch(): void
    {
        $card = $this->guardedCard();

        $card->watchers()->toggle(Auth::id());
    }

    public function toggleMember(int $userId): void
    {
        $card = $this->guardedCard();

        if (! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $result = $card->members()->toggle($userId);

        if (in_array($userId, $result['attached'], true)) {
            // Fires for self-assignment too ("Rejoindre") — rules like
            // "when I'm assigned" must see it.
            app(AutomationEngine::class)->fire('card.member_assigned', $card, ['user_id' => $userId]);
        }

        if (in_array($userId, $result['attached'], true) && $userId !== Auth::id()) {
            $assignee = User::find($userId);

            $this->logActivity($card, 'member.assigned', ['user_id' => $userId, 'user_name' => $assignee?->name]);

            if ($assignee) {
                $assignee->notify(new CardNotification($card, 'assigned', Auth::user()));
            }
        }

        $this->touched('card.members');
    }
}
