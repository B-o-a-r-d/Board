<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardCompletedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.completed';
    }

    public function label(): string
    {
        return 'Quand une carte est marquée terminée';
    }

    public function events(): array
    {
        return ['card.completed'];
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
