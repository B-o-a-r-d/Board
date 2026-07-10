<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class ArchiveListCardsAction implements AutomationAction
{
    public static function key(): string
    {
        return 'archive_list_cards';
    }

    public function label(): string
    {
        return "Archiver toutes les cartes d'une liste";
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste (optionnel — vide = liste de la carte)', 'type' => 'list'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;

        if (! $card->board->lists()->whereKey($listId)->exists()) {
            return;
        }

        Card::query()
            ->where('board_list_id', $listId)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }
}
