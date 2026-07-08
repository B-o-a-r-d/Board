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
}
