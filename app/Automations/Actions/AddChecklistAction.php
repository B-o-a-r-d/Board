<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class AddChecklistAction implements AutomationAction
{
    public static function key(): string
    {
        return 'add_checklist';
    }

    public function label(): string
    {
        return 'Ajouter une checklist';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titre de la checklist', 'type' => 'text'],
            ['key' => 'items', 'label' => 'Éléments (séparés par des virgules ou des retours à la ligne)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $title = trim((string) ($config['title'] ?? '')) ?: 'Checklist';

        $checklist = $card->checklists()->create([
            'title' => mb_substr($title, 0, 255),
            'position' => (int) $card->checklists()->max('position') + 1,
        ]);

        $items = collect(preg_split('/[,\n]/', (string) ($config['items'] ?? '')))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values();

        foreach ($items as $index => $content) {
            $checklist->items()->create([
                'content' => mb_substr($content, 0, 255),
                'position' => $index,
            ]);
        }
    }
}
