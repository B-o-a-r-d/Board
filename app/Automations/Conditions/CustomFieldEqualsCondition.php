<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class CustomFieldEqualsCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'custom_field_equals';
    }

    public function label(): string
    {
        return 'Le champ personnalisé vaut';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'field_id', 'label' => 'Champ', 'type' => 'custom_field'],
            ['key' => 'value', 'label' => 'Valeur', 'type' => 'text'],
        ];
    }

    public function passes(Card $card, array $config): bool
    {
        $fieldId = (int) ($config['field_id'] ?? 0);

        if ($fieldId === 0) {
            return false;
        }

        $stored = $card->customFieldValues()->where('custom_field_id', $fieldId)->value('value');

        return $stored !== null && $stored === (string) ($config['value'] ?? '');
    }
}
