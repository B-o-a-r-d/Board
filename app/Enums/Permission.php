<?php

namespace App\Enums;

/**
 * Atomic capabilities the app checks. Roles (see the roles table, seeded from
 * the Role enum) hold a set of these; the BoardPolicy resolves abilities to
 * permission checks via Board::userCan().
 */
enum Permission: string
{
    case BoardView = 'board.view';
    case CardManage = 'card.manage';
    case CommentPost = 'comment.post';
    case BoardSettings = 'board.settings';
    case MemberManage = 'member.manage';
    case PluginManage = 'plugin.manage';
    case BoardDelete = 'board.delete';

    public function label(): string
    {
        return match ($this) {
            self::BoardView => 'Voir le tableau',
            self::CardManage => 'Gérer les cartes',
            self::CommentPost => 'Commenter',
            self::BoardSettings => 'Paramètres du tableau',
            self::MemberManage => 'Gérer les membres et rôles',
            self::PluginManage => 'Gérer les Power-Ups',
            self::BoardDelete => 'Supprimer le tableau',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BoardView => 'Accéder au tableau et à son contenu en lecture.',
            self::CardManage => 'Créer, modifier, déplacer, archiver des cartes, checklists, dates, labels et membres.',
            self::CommentPost => 'Publier des commentaires et réagir.',
            self::BoardSettings => 'Renommer, fond, listes, champs personnalisés, limites WIP.',
            self::MemberManage => 'Inviter/retirer des membres et changer leurs rôles.',
            self::PluginManage => 'Installer et configurer les Power-Ups.',
            self::BoardDelete => 'Supprimer définitivement le tableau.',
        };
    }

    /**
     * Grouping for the admin UI.
     */
    public function group(): string
    {
        return match ($this) {
            self::BoardView, self::CardManage, self::CommentPost => 'Contenu',
            default => 'Administration',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
