<?php

namespace App\Enums;

enum BoardVisibility: string
{
    case Private = 'private';
    case Workspace = 'workspace';

    /**
     * Human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Private => __('Privé (membres du board)'),
            self::Workspace => __('Workspace (tous les membres du workspace)'),
        };
    }

    /**
     * Phosphor icon name (without the "phosphor-" prefix) shown in place of the
     * verbose label; pair it with {@see self::label()} as a tooltip.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Private => 'lock-simple',
            self::Workspace => 'users-three',
        };
    }
}
