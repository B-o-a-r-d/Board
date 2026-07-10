<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class CopyCardAction implements AutomationAction
{
    public static function key(): string
    {
        return 'copy_card';
    }

    public function label(): string
    {
        return 'Copier la carte vers une liste';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'list_id', 'label' => 'Liste de destination', 'type' => 'list'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;
        $list = $card->board->lists()->whereKey($listId)->first();

        if ($list === null) {
            return;
        }

        $copy = $list->cards()->create([
            'board_id' => $card->board_id,
            'created_by' => Auth::id(),
            'title' => $card->title,
            'description' => $card->description,
            'cover_color' => $card->cover_color,
            'due_at' => $card->due_at,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $copy->labels()->attach($card->labels()->pluck('labels.id'));
        $copy->members()->attach($card->members()->pluck('users.id'));
    }
}
