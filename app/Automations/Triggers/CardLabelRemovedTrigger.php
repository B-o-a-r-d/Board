<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardLabelRemovedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.label_removed';
    }

    public function label(): string
    {
        return 'Quand un label est retiré d’une carte';
    }

    public function events(): array
    {
        return ['card.label_removed'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'label_id', 'label' => 'Label (optionnel — vide = tous)', 'type' => 'label'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return empty($config['label_id'])
            || (int) ($payload['label_id'] ?? 0) === (int) $config['label_id'];
    }
}
