<?php

namespace App\Automations;

use Illuminate\Support\Carbon;

/**
 * Decides whether a scheduled rule is due. The runner ticks every few minutes;
 * a rule fires the first tick past its occurrence instant, and `last_run_at`
 * (updated by the pipeline) makes every occurrence fire exactly once.
 *
 * trigger_config shape:
 *   freq: daily | days | every_n_weeks | monthly_first_dow | monthly_day | yearly
 *   at:   'HH:MM' (default 09:00)
 *   days: ['monday', …]        (days / every_n_weeks)
 *   n:    2                    (every_n_weeks)
 *   anchor: 'Y-m-d'            (every_n_weeks — week-parity anchor, default 2001-01-01)
 *   dow:  'monday'             (monthly_first_dow)
 *   day:  1-31                 (monthly_day — clamped to the month length; yearly)
 *   month: 1-12                (yearly)
 */
class ScheduleMatcher
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function isDue(array $config, Carbon $now, ?Carbon $lastRunAt): bool
    {
        $occurrence = $this->occurrenceFor($config, $now);

        return $occurrence !== null
            && $now->gte($occurrence)
            && ($lastRunAt === null || $lastRunAt->lt($occurrence));
    }

    /**
     * Today's occurrence instant, or null when today doesn't match the frequency.
     *
     * @param  array<string, mixed>  $config
     */
    public function occurrenceFor(array $config, Carbon $now): ?Carbon
    {
        $matches = match ($config['freq'] ?? '') {
            'daily' => true,
            'days' => $this->dayMatches($config, $now),
            'every_n_weeks' => $this->dayMatches($config, $now) && $this->weekMatches($config, $now),
            'monthly_first_dow' => $now->day <= 7
                && strtolower($now->englishDayOfWeek) === strtolower((string) ($config['dow'] ?? 'monday')),
            'monthly_day' => $now->day === min(max(1, (int) ($config['day'] ?? 1)), $now->daysInMonth),
            'yearly' => $now->month === (int) ($config['month'] ?? 1)
                && $now->day === min(max(1, (int) ($config['day'] ?? 1)), $now->daysInMonth),
            default => false,
        };

        if (! $matches) {
            return null;
        }

        [$hour, $minute] = preg_match('/^(\d{1,2}):(\d{2})$/', (string) ($config['at'] ?? ''), $m)
            ? [min(23, (int) $m[1]), min(59, (int) $m[2])]
            : [9, 0];

        return $now->copy()->setTime($hour, $minute, 0);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dayMatches(array $config, Carbon $now): bool
    {
        $days = array_map(strtolower(...), array_filter((array) ($config['days'] ?? []), 'is_string'));

        return in_array(strtolower($now->englishDayOfWeek), $days, true);
    }

    /**
     * Week parity for "every N weeks": weeks elapsed since the anchor's week
     * start must be a multiple of N.
     *
     * @param  array<string, mixed>  $config
     */
    private function weekMatches(array $config, Carbon $now): bool
    {
        $n = max(1, (int) ($config['n'] ?? 1));
        $anchorWeek = Carbon::parse((string) ($config['anchor'] ?? '2001-01-01'))->startOfWeek();
        $weeks = (int) $anchorWeek->diffInWeeks($now->copy()->startOfWeek());

        return $weeks % $n === 0;
    }
}
