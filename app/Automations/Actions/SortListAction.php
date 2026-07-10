<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class SortListAction implements AutomationAction
{
    public static function key(): string
    {
        return 'sort_list';
    }

    public function label(): string
    {
        return 'Trier une liste';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste (optionnel — vide = liste de la carte)', 'type' => 'list'],
            ['key' => 'by', 'label' => 'Critère (due | title | created)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;

        if (! $card->board->lists()->whereKey($listId)->exists()) {
            return;
        }

        $ordered = Card::query()
            ->where('board_list_id', $listId)
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (Card $c) => match ($config['by'] ?? 'due') {
                'title' => mb_strtolower($c->title),
                'created' => $c->created_at?->getTimestamp() ?? 0,
                // Undated cards sink to the bottom.
                default => $c->due_at?->getTimestamp() ?? PHP_INT_MAX,
            })
            ->values();

        foreach ($ordered as $index => $sorted) {
            $sorted->update(['position' => $index]);
        }
    }
}
