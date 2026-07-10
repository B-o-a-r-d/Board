<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

/**
 * Due-date rules ("3 days before/after a card's due date"). Schedule-driven:
 * the automations:run-scheduled command finds the cards whose relative instant
 * (due_at ± N days) just crossed and runs the pipeline on each of them.
 */
class CardDueRelativeTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.due_relative';
    }

    public function label(): string
    {
        return "N jours avant / après l'échéance";
    }

    public function events(): array
    {
        return [];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'days', 'label' => 'Nombre de jours', 'type' => 'number'],
            ['key' => 'direction', 'label' => 'Sens (before | after)', 'type' => 'text'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return false;
    }
}
