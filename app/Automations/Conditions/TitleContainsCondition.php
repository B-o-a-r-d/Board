<?php

namespace App\Automations\Conditions;

use App\Automations\Contracts\AutomationCondition;
use App\Models\Card;

class TitleContainsCondition implements AutomationCondition
{
    public static function key(): string
    {
        return 'title_contains';
    }

    public function label(): string
    {
        return 'Le titre contient';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'text', 'label' => 'Texte recherché', 'type' => 'text'],
        ];
    }

    public function passes(Card $card, array $config): bool
    {
        $needle = trim((string) ($config['text'] ?? ''));

        return $needle !== '' && str_contains(mb_strtolower($card->title), mb_strtolower($needle));
    }
}
