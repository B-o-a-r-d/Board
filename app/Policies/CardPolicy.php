<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    public function __construct(private readonly BoardPolicy $boardPolicy) {}

    /**
     * A user who can view the board can view its cards.
     */
    public function view(User $user, Card $card): bool
    {
        return $this->boardPolicy->view($user, $card->board);
    }

    /**
     * A contributing member (not a read-only Observer) can edit the board's cards.
     */
    public function update(User $user, Card $card): bool
    {
        return $this->boardPolicy->contribute($user, $card->board);
    }

    public function delete(User $user, Card $card): bool
    {
        return $this->boardPolicy->contribute($user, $card->board);
    }
}
