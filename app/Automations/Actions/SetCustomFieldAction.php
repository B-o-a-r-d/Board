<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Enums\CustomFieldType;
use App\Models\Card;

class SetCustomFieldAction implements AutomationAction
{
    public static function key(): string
    {
        return 'set_custom_field';
    }

    public function label(): string
    {
        return 'Définir un champ personnalisé';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'field_id', 'label' => 'Champ', 'type' => 'custom_field'],
            ['key' => 'value', 'label' => 'Valeur (vide = effacer)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $field = $card->board->customFields()->whereKey((int) ($config['field_id'] ?? 0))->first();

        if ($field === null || ! $field->appliesToCard($card)) {
            return;
        }

        $value = $config['value'] ?? null;

        $stored = match ($field->type) {
            CustomFieldType::Checkbox => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : null,
            CustomFieldType::Select => in_array($value, $field->optionList(), true) ? $value : null,
            default => ($value === '' || $value === null) ? null : mb_substr(trim((string) $value), 0, 1000),
        };

        if ($stored === null) {
            $card->customFieldValues()->where('custom_field_id', $field->id)->delete();
        } else {
            $card->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }
    }
}
