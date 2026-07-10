<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class SetDueDateAction implements AutomationAction
{
    public static function key(): string
    {
        return 'set_due_date';
    }

    public function label(): string
    {
        return 'Définir une échéance (dans N jours)';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'days', 'label' => 'Dans N jours (0 = aujourd’hui)', 'type' => 'number'],
            ['key' => 'time', 'label' => 'Heure (HH:MM, défaut 12:00)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $days = max(0, (int) ($config['days'] ?? 0));
        $time = (string) ($config['time'] ?? '');

        [$hour, $minute] = preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)
            ? [min(23, (int) $m[1]), min(59, (int) $m[2])]
            : [12, 0];

        $card->update(['due_at' => now()->addDays($days)->setTime($hour, $minute)]);
    }
}
