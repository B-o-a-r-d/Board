<?php

namespace App\Automations\Triggers;

use App\Automations\Contracts\AutomationTrigger;
use App\Automations\ScheduleMatcher;
use App\Models\Card;

/**
 * Time-driven rules ("every day at 09:00…"). They never react to app events:
 * the automations:run-scheduled command evaluates their planning config via
 * {@see ScheduleMatcher} and runs a board-scope pipeline.
 */
class ScheduledTrigger implements AutomationTrigger
{
    public static function key(): string
    {
        return 'scheduled';
    }

    public function label(): string
    {
        return 'Programmée (tous les jours / semaines / mois…)';
    }

    public function events(): array
    {
        return [];
    }

    public function configFields(): array
    {
        return [
            ['key' => 'freq', 'label' => 'Fréquence (daily | days | every_n_weeks | monthly_first_dow | monthly_day | yearly)', 'type' => 'text'],
            ['key' => 'at', 'label' => 'Heure (HH:MM, défaut 09:00)', 'type' => 'text'],
            ['key' => 'days', 'label' => 'Jours (monday…sunday, selon fréquence)', 'type' => 'text'],
            ['key' => 'n', 'label' => 'Toutes les N semaines', 'type' => 'number'],
            ['key' => 'dow', 'label' => 'Premier jour du mois (monday…)', 'type' => 'text'],
            ['key' => 'day', 'label' => 'Jour du mois / de l’année', 'type' => 'number'],
            ['key' => 'month', 'label' => 'Mois (1-12, fréquence yearly)', 'type' => 'number'],
        ];
    }

    public function matches(string $event, Card $card, array $config, array $payload): bool
    {
        return false;
    }
}
