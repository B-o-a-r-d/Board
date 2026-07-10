<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardMovedFromListTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.moved_from_list';
    }

    public function label(): string
    {
        return 'Quand une carte quitte une liste';
    }

    public function events(): array
    {
        return ['card.moved'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste quittée', 'type' => 'list'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return ! empty($config['list_id'])
            && (int) ($payload['from_list_id'] ?? 0) === (int) $config['list_id']
            && (int) ($payload['to_list_id'] ?? 0) !== (int) $config['list_id'];
    }
}
