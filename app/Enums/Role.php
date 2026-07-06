<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Member => 'Membre',
        };
    }

    /**
     * Roles that can administrate (manage members, settings, delete).
     */
    public function isAdministrator(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
