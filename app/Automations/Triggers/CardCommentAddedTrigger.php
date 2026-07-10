<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Models\Card;

class CardCommentAddedTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'comment.added';
    }

    public function label(): string
    {
        return 'Quand un commentaire est ajouté';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'text', 'label' => 'Contenant le texte (optionnel)', 'type' => 'text'],
        ];
    }

    public function events(): array
    {
        return ['comment.added'];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        $needle = trim((string) ($config['text'] ?? ''));

        if ($needle === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) ($payload['body'] ?? '')), mb_strtolower($needle));
    }
}
