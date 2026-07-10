<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class MarkIncompleteAction implements AutomationAction
{
    public static function key(): string
    {
        return 'mark_incomplete';
    }

    public function label(): string
    {
        return 'Marquer la carte non terminée';
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        $card->update(['completed_at' => null]);
    }
}
