<?php

namespace App\Livewire\Cards\Concerns;

use App\Automations\AutomationEngine;
use App\Enums\CustomFieldType;
use App\Models\Card;
use Illuminate\Validation\Rule;

/**
 * Custom field values on the open card + card-scoped field creation.
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesCardCustomFields
{
    public string $newCfName = '';

    public string $newCfType = 'text';

    /** Comma-separated options for the select/multiselect types. */
    public string $newCfOptions = '';

    /** Field scope: card (this card only), list (inherited by its cards), board. */
    public string $newCfScope = 'card';

    /**
     * Set (or clear) a custom field value on the current card. A null/empty
     * value removes the stored row so the field reads as unset.
     */
    public function saveCustomField(int $fieldId, mixed $value): void
    {
        $card = $this->guardedCard();

        $field = $this->board->customFields()->visibleOn($this->board)->findOrFail($fieldId);

        // Scoped fields (list / single card) only accept values where they apply.
        abort_unless($field->appliesToCard($card), 404);

        $this->resetErrorBag('cf-'.$field->id);

        // Invalid input keeps the previous value instead of silently clearing it.
        if (in_array($field->type, [CustomFieldType::Url, CustomFieldType::Email], true)
            && is_string($value) && trim($value) !== '') {
            $trimmed = trim($value);
            $invalid = $field->type === CustomFieldType::Url
                ? ! CustomFieldType::isSafeUrl($trimmed)
                : filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false;

            if ($invalid) {
                $this->addError('cf-'.$field->id, $field->type === CustomFieldType::Url
                    ? __('URL invalide (http/https requis).')
                    : __('Adresse email invalide.'));

                return;
            }
        }

        $stored = match ($field->type) {
            CustomFieldType::Checkbox => $value ? '1' : null,
            CustomFieldType::Select => in_array($value, $field->optionList(), true) ? $value : null,
            CustomFieldType::MultiSelect => $this->encodeMultiSelect($field->optionList(), $value),
            CustomFieldType::Member => $this->normalizeMemberValue($value),
            CustomFieldType::Money => is_numeric(str_replace(',', '.', trim((string) $value)))
                ? (string) (float) str_replace(',', '.', trim((string) $value))
                : null,
            CustomFieldType::Rating => ((int) $value > 0) ? (string) min(5, (int) $value) : null,
            CustomFieldType::Progress => ($value === '' || $value === null) ? null : (string) max(0, min(100, (int) $value)),
            default => ($value === '' || $value === null) ? null : (string) trim((string) $value),
        };

        if ($stored === null) {
            $card->customFieldValues()->where('custom_field_id', $field->id)->delete();
        } else {
            $card->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }

        app(AutomationEngine::class)->fire('custom_field.changed', $card, [
            'field_id' => $field->id,
            'value' => $stored,
        ]);

        $this->touched('card.updated');
    }

    /**
     * Create a custom field from the card's "Add to card" menu. Card and list
     * scopes are contributor actions; a board-wide field stays admin-only.
     */
    public function addCardCustomField(): void
    {
        $card = $this->guardedCard();

        $data = $this->validate([
            'newCfName' => ['required', 'string', 'max:60'],
            'newCfType' => ['required', 'string', Rule::enum(CustomFieldType::class)],
            'newCfScope' => ['required', 'string', 'in:card,list,board'],
        ]);

        if ($data['newCfScope'] === 'board') {
            $this->authorize('update', $this->board);
        }

        $type = CustomFieldType::from($data['newCfType']);
        $options = null;

        if ($type->hasOptions()) {
            $options = collect(explode(',', $this->newCfOptions))
                ->map(fn (string $option): string => trim($option))
                ->filter()
                ->values()
                ->all();

            if (empty($options)) {
                $this->addError('newCfOptions', __('Ajoutez au moins une option.'));

                return;
            }
        }

        $this->board->customFields()->create([
            'board_list_id' => $data['newCfScope'] === 'list' ? $card->board_list_id : null,
            'card_id' => $data['newCfScope'] === 'card' ? $card->id : null,
            'name' => $data['newCfName'],
            'type' => $type,
            'options' => $options,
            'position' => (int) $this->board->customFields()->max('position') + 1,
        ]);

        $this->reset('newCfName', 'newCfOptions');
        $this->newCfType = 'text';
        $this->newCfScope = 'card';
        $this->dispatch('card-field-added');
        $this->touched('card.updated');
    }

    /**
     * Keep only declared options, JSON-encoded; null when nothing remains.
     *
     * @param  array<int, string>  $options
     */
    private function encodeMultiSelect(array $options, mixed $value): ?string
    {
        $picked = array_values(array_intersect(
            array_map(strval(...), is_array($value) ? $value : (array) $value),
            $options,
        ));

        return $picked === [] ? null : (string) json_encode($picked);
    }

    /**
     * A Member field only accepts an actual member of this board.
     */
    private function normalizeMemberValue(mixed $value): ?string
    {
        $id = (int) $value;

        return ($id > 0 && $this->board->members()->whereKey($id)->exists()) ? (string) $id : null;
    }
}
