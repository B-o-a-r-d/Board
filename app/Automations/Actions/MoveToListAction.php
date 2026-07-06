<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class MoveToListAction implements AutomationAction
{
    public static function key(): string
    {
        return 'move_to_list';
    }

    public function label(): string
    {
        return 'Déplacer la carte vers une liste';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste de destination', 'type' => 'list'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0);

        if ($listId === 0 || $card->board_list_id === $listId) {
            return;
        }

        $list = $card->board->lists()->whereKey($listId)->first();

        if ($list === null) {
            return;
        }

        $card->update([
            'board_list_id' => $list->id,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);
    }
}
