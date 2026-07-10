<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class ChecklistCompletedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'checklist.completed';
    }

    public function label(): string
    {
        return 'Quand une checklist est entièrement cochée';
    }

    public function events(): array
    {
        return ['checklist.completed'];
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
