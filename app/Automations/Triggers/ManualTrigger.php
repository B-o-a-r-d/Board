<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

/**
 * A manual trigger turns an automation into a card button run on demand
 * (see AutomationEngine::runManual), never fired by app events.
 */
class ManualTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Bouton manuel sur la carte';
    }

    public function events(): array
    {
        return [];
    }

    public function configFields(): array
    {
        return [];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return false;
    }
}
