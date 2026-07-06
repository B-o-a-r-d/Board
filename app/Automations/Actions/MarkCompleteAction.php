<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class MarkCompleteAction implements AutomationAction
{
    public static function key(): string
    {
        return 'mark_complete';
    }

    public function label(): string
    {
        return 'Marquer la carte comme terminée';
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        if ($card->completed_at === null) {
            $card->update(['completed_at' => now()]);
        }
    }
}
