<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class MoveInListAction implements AutomationAction
{
    public static function key(): string
    {
        return 'move_in_list';
    }

    public function label(): string
    {
        return 'Déplacer la carte en haut / bas de sa liste';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'position', 'label' => 'Position (top | bottom)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $siblings = $card->list->cards();

        if (($config['position'] ?? 'top') === 'top') {
            // Positions are unsigned: shift the list down, land at 0.
            $siblings->whereKeyNot($card->id)->increment('position');
            $card->update(['position' => 0]);

            return;
        }

        $card->update(['position' => (int) $siblings->max('position') + 1]);
    }
}
