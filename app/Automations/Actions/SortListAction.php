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
            ['key' => 'dir', 'label' => 'Sens (asc | desc — asc par défaut)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;

        if (! $card->board->lists()->whereKey($listId)->exists()) {
            return;
        }

        $by = in_array($config['by'] ?? 'due', ['due', 'title', 'created'], true) ? ($config['by'] ?? 'due') : 'due';
        $dir = ($config['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $key = fn (Card $c): mixed => match ($by) {
            'title' => mb_strtolower($c->title),
            'created' => $c->created_at?->getTimestamp() ?? 0,
            default => $c->due_at?->getTimestamp() ?? 0,
        };

        $cards = Card::query()
            ->where('board_list_id', $listId)
            ->whereNull('archived_at')
            ->get();

        // Undated cards sink to the bottom in BOTH directions.
        [$dated, $undated] = $by === 'due'
            ? $cards->partition(fn (Card $c): bool => $c->due_at !== null)
            : [$cards, collect()];

        $ordered = ($dir === 'desc' ? $dated->sortByDesc($key) : $dated->sortBy($key))
            ->concat($undated)
            ->values();

        foreach ($ordered as $index => $sorted) {
            $sorted->update(['position' => $index]);
        }

        // Remembered so the list menu shows the direction arrow and a second
        // click on the same criterion inverts it.
        $card->board->lists()->whereKey($listId)->update([
            'last_sorted_by' => $by,
            'last_sorted_dir' => $dir,
        ]);
    }
}
