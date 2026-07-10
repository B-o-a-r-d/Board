<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class CreateCardAction implements AutomationAction
{
    public static function key(): string
    {
        return 'create_card';
    }

    public function label(): string
    {
        return 'Créer une carte';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titre de la carte', 'type' => 'text'],
            ['key' => 'list_id', 'label' => 'Liste (optionnel — vide = liste de la carte)', 'type' => 'list'],
            ['key' => 'unique', 'label' => 'Unique (ne pas recréer si le titre existe déjà)', 'type' => 'checkbox'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $title = trim((string) ($config['title'] ?? ''));

        if ($title === '') {
            return;
        }

        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;
        $list = $card->board->lists()->whereKey($listId)->first();

        if ($list === null) {
            return;
        }

        // Butler's "unique" option: skip when a live card already carries the title.
        if (! empty($config['unique']) && $list->cards()->whereNull('archived_at')->where('title', $title)->exists()) {
            return;
        }

        $list->cards()->create([
            'board_id' => $card->board_id,
            'created_by' => Auth::id(),
            'title' => mb_substr($title, 0, 255),
            'position' => (int) $list->cards()->max('position') + 1,
        ]);
    }
}
