<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class HasDueDateCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'has_due_date';
    }

    public function label(): string
    {
        return 'La carte a une échéance';
    }

    public function configFields(): array
    {
        return [];
    }

    public function passes(Card $card, array $config): bool
    {
        return $card->due_at !== null;
    }
}
