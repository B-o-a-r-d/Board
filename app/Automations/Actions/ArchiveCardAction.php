<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class ArchiveCardAction implements AutomationAction
{
    public static function key(): string
    {
        return 'archive_card';
    }

    public function label(): string
    {
        return 'Archiver la carte';
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        if ($card->archived_at === null) {
            $card->update(['archived_at' => now()]);
        }
    }
}
