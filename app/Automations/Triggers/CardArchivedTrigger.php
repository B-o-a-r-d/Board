<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardArchivedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.archived';
    }

    public function label(): string
    {
        return 'Quand une carte est archivée';
    }

    public function events(): array
    {
        return ['card.archived'];
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
