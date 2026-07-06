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
     * Any member who can view the board can edit its cards (Trello-style collaboration).
     */
    public function update(User $user, Card $card): bool
    {
        return $this->boardPolicy->view($user, $card->board);
    }

    public function delete(User $user, Card $card): bool
    {
        return $this->boardPolicy->view($user, $card->board);
    }
}
