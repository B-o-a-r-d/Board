<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardCreatedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.created';
    }

    public function label(): string
    {
        return 'Quand une carte est créée';
    }

    public function events(): array
    {
        return ['card.created'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Dans la liste (optionnel)', 'type' => 'list'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        if (! empty($config['list_id'])) {
            return (int) ($payload['list_id'] ?? 0) === (int) $config['list_id'];
        }

        return true;
    }
}
