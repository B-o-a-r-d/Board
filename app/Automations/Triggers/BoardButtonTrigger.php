<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

/**
 * A manual board-level button (topbar): clicking it runs a board-scope
 * pipeline (create a card, sort a list, archive a list's cards…) on a phantom
 * card, exactly like scheduled rules. Never fired by app events.
 */
class BoardButtonTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'board_button';
    }

    public function label(): string
    {
        return 'Bouton manuel sur le tableau';
    }

    public function events(): array
    {
        return [];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'icon', 'label' => 'Icône (Phosphor)', 'type' => 'text'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return false;
    }
}
