<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class RemoveLabelAction implements AutomationAction
{
    public static function key(): string
    {
        return 'remove_label';
    }

    public function label(): string
    {
        return 'Retirer un label';
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

        if ($labelId > 0) {
            $card->labels()->detach($labelId);
        }
    }
}
