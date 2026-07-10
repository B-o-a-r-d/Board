<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CustomFieldChangedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'custom_field.changed';
    }

    public function label(): string
    {
        return 'Quand un champ personnalisé change';
    }

    public function events(): array
    {
        return ['custom_field.changed'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'field_id', 'label' => 'Champ', 'type' => 'custom_field'],
            ['key' => 'value', 'label' => 'Prend la valeur (optionnel — vide = tout changement)', 'type' => 'text'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        $fieldId = (int) ($config['field_id'] ?? 0);

        if ($fieldId === 0 || (int) ($payload['field_id'] ?? 0) !== $fieldId) {
            return false;
        }

        $expected = trim((string) ($config['value'] ?? ''));

        return $expected === '' || (string) ($payload['value'] ?? '') === $expected;
    }
}
