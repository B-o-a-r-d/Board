<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardTitleContainsTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'card.title_contains';
    }

    public function label(): string
    {
        return 'Quand le titre d’une carte contient';
    }

    public function events(): array
    {
        return ['card.created', 'card.renamed'];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'text', 'label' => 'Texte recherché', 'type' => 'text'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        $needle = trim((string) ($config['text'] ?? ''));

        return $needle !== '' && str_contains(mb_strtolower($card->title), mb_strtolower($needle));
    }
}
