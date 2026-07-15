<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Url = 'url';
    case Email = 'email';
    case MultiSelect = 'multiselect';
    case Member = 'member';
    case Money = 'money';
    case Rating = 'rating';
    case Progress = 'progress';

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
            self::Url => __('Lien (URL)'),
            self::Email => __('Email'),
            self::MultiSelect => __('Choix multiples'),
            self::Member => __('Membre'),
            self::Money => __('Montant'),
            self::Rating => __('Note (étoiles)'),
            self::Progress => __('Progression'),
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
            self::Url => 'link',
            self::Email => 'envelope-simple',
            self::MultiSelect => 'list-plus',
            self::Member => 'user',
            self::Money => 'currency-circle-dollar',
            self::Rating => 'star',
            self::Progress => 'percent',
        };
    }

    /**
     * Whether this type stores a curated list of options.
     */
    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }

    /**
     * Whether stored values are JSON-encoded (decoded via {@see CustomField::decode()}).
     */
    public function storesJson(): bool
    {
        return $this === self::MultiSelect;
    }

    /**
     * A Url-field value is only safe to store and to render into an href when it
     * is a well-formed http(s) URL. This is the single guard against a stored
     * `javascript:`/`data:` scheme becoming one-click XSS when a viewer clicks
     * the link — enforced on every write path and again at render.
     */
    public static function isSafeUrl(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== ''
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
