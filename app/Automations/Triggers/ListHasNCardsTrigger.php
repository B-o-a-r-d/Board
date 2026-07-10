<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

/**
 * "When list X has exactly / at least N cards" — evaluated when a card lands
 * in a list (created in or moved into it); the pipeline then applies to that
 * newly-arrived card (e.g. move it elsewhere, archive it).
 */
class ListHasNCardsTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'list.has_n_cards';
    }

    public function label(): string
    {
        return 'Quand une liste atteint N cartes';
    }

    public function events(): array
    {
        return ['card.created', 'card.moved'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste surveillée', 'type' => 'list'],
            ['key' => 'op', 'label' => 'Comparaison (exactly | at_least)', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Nombre de cartes', 'type' => 'number'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        $listId = (int) ($config['list_id'] ?? 0);
        $count = (int) ($config['count'] ?? 0);

        if ($listId === 0 || $count <= 0 || $card->board_list_id !== $listId) {
            return false;
        }

        $inList = Card::query()
            ->where('board_list_id', $listId)
            ->whereNull('archived_at')
            ->count();

        return ($config['op'] ?? 'at_least') === 'exactly'
            ? $inList === $count
            : $inList >= $count;
    }
}
