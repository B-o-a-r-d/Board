<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardDueDateSetTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.due_set';
    }

    public function label(): string
    {
        return 'Quand une échéance est définie sur une carte';
    }

    public function events(): array
    {
        return ['card.due_set'];
    }

    public function configFields(): array
    {
        return [];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return true;
    }
}
