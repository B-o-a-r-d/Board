<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class InListCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'in_list';
    }

    public function label(): string
    {
        return 'La carte est dans la liste';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste', 'type' => 'list'],
        ];
    }

    public function passes(Card $card, array $config): bool
    {
        $listId = (int) ($config['list_id'] ?? 0);

        return $listId > 0 && $card->board_list_id === $listId;
    }
}
