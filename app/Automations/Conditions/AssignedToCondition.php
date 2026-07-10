<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class AssignedToCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'assigned_to';
    }

    public function label(): string
    {
        return 'La carte est assignée à';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'user_id', 'label' => 'Membre', 'type' => 'member'],
        ];
    }

    public function passes(Card $card, array $config): bool
    {
        $userId = (int) ($config['user_id'] ?? 0);

        return $userId > 0 && $card->members()->whereKey($userId)->exists();
    }
}
