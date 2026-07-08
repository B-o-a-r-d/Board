<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';

    /**
     * Human readable label (French source; translated via JSON lang files).
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => __('Texte'),
            self::Number => __('Nombre'),
            self::Date => __('Date'),
            self::Select => __('Liste déroulante'),
            self::Checkbox => __('Case à cocher'),
        };
    }

    /**
     * Phosphor icon name representing this field type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Text => 'text-aa',
            self::Number => 'hash',
            self::Date => 'calendar-blank',
            self::Select => 'list-checks',
            self::Checkbox => 'check-square',
        };
    }

    /**
     * Whether this type stores a curated list of options.
     */
    public function hasOptions(): bool
    {
        return $this === self::Select;
    }
}
