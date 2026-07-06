<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardDueSoonTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.due_soon';
    }

    public function label(): string
    {
        return "Quand l'échéance d'une carte approche";
    }

    public function events(): array
    {
        return ['card.due_soon'];
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
