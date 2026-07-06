<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class AssignLabelAction implements AutomationAction
{
    public static function key(): string
    {
        return 'assign_label';
    }

    public function label(): string
    {
        return 'Assigner un label';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'label_id', 'label' => 'Label', 'type' => 'label'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $labelId = (int) ($config['label_id'] ?? 0);

        if ($labelId === 0) {
            return;
        }

        // Only labels belonging to the card's board can be attached.
        if ($card->board->labels()->whereKey($labelId)->exists()) {
            $card->labels()->syncWithoutDetaching([$labelId]);
        }
    }
}
