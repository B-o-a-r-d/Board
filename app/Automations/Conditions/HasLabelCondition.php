<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class HasLabelCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'has_label';
    }

    public function label(): string
    {
        return 'La carte a le label';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'label_id', 'label' => 'Label', 'type' => 'label'],
        ];
    }

    public function passes(Card $card, array $config): bool
    {
        $labelId = (int) ($config['label_id'] ?? 0);

        return $labelId > 0 && $card->labels()->whereKey($labelId)->exists();
    }
}
