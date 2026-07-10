<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class ChecklistItemCheckedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'checklist.item_checked';
    }

    public function label(): string
    {
        return 'Quand un élément de checklist est coché';
    }

    public function events(): array
    {
        return ['checklist.item_checked'];
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
