<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class ChecklistAddedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'checklist.added';
    }

    public function label(): string
    {
        return 'Quand une checklist est ajoutée à une carte';
    }

    public function events(): array
    {
        return ['checklist.added'];
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
