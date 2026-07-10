<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use App\Models\CardLink;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateFollowUpCardAction implements AutomationAction
{
    public static function key(): string
    {
        return 'create_follow_up_card';
    }

    public function label(): string
    {
        return 'Créer une carte de suivi (liée)';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titre (optionnel — défaut « Suivi : … »)', 'type' => 'text'],
            ['key' => 'list_id', 'label' => 'Liste (optionnel — vide = liste de la carte)', 'type' => 'list'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $listId = (int) ($config['list_id'] ?? 0) ?: $card->board_list_id;
        $list = $card->board->lists()->whereKey($listId)->first();

        if ($list === null) {
            return;
        }

        $title = trim((string) ($config['title'] ?? '')) ?: 'Suivi : '.Str::limit($card->title, 200);

        $followUp = $list->cards()->create([
            'board_id' => $card->board_id,
            'created_by' => Auth::id(),
            'title' => mb_substr($title, 0, 255),
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        CardLink::firstOrCreate([
            'card_id' => min($card->id, $followUp->id),
            'related_card_id' => max($card->id, $followUp->id),
            'type' => 'relates_to',
        ]);
    }
}
