<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class ClearDueDateAction implements AutomationAction
{
    public static function key(): string
    {
        return 'clear_due_date';
    }

    public function label(): string
    {
        return "Retirer l'échéance";
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        $card->update(['due_at' => null]);
    }
}
