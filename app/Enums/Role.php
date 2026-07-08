<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Observer = 'observer';

    /**
     * Human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Member => 'Membre',
            self::Observer => 'Observateur',
        };
    }

    /**
     * Roles that can administrate (manage members, settings, delete).
     */
    public function isAdministrator(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Default permission set for this system role — the seed for the per-workspace
     * `roles` rows. Custom roles override these freely.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => Permission::cases(),
            self::Member => [Permission::BoardView, Permission::CardManage, Permission::CommentPost],
            self::Observer => [Permission::BoardView],
        };
    }
}
