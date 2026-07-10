<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardMemberAssignedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.member_assigned';
    }

    public function label(): string
    {
        return 'Quand un membre est assigné à une carte';
    }

    public function events(): array
    {
        return ['card.member_assigned'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'user_id', 'label' => 'Membre (optionnel — vide = tous)', 'type' => 'member'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return empty($config['user_id'])
            || (int) ($payload['user_id'] ?? 0) === (int) $config['user_id'];
    }
}
