<?php

namespace App\Console\Commands;

use App\Automations\AutomationEngine;
use App\Automations\ScheduleMatcher;
use App\Models\Automation;
use App\Models\Card;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

#[Signature('automations:run-scheduled')]
#[Description('Run time-based automations: due-soon triggers, scheduled rules and due-date (±N days) rules.')]
class RunScheduledAutomations extends Command
{
    public function handle(AutomationEngine $engine, ScheduleMatcher $matcher): int
    {
        $now = Carbon::now();

        $ran = $this->fireDueSoon($engine)
            + $this->runScheduledRules($engine, $matcher, $now)
            + $this->runDueRelativeRules($engine, $now);

        $this->info("Ran {$ran} automation action(s).");

        return self::SUCCESS;
    }

    /**
     * Historic behaviour: fire card.due_soon for cards due within ±24h on
     * boards that use the trigger.
     */
    private function fireDueSoon(AutomationEngine $engine): int
    {
        $boardIds = Automation::query()
            ->where('is_active', true)
            ->where('trigger_type', 'card.due_soon')
            ->distinct()
            ->pluck('board_id');

        if ($boardIds->isEmpty()) {
            return 0;
        }

        $cards = Card::query()
            ->whereIn('board_id', $boardIds)
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now()->subDay(), now()->addDay()])
            ->get();

        $ran = 0;

        foreach ($cards as $card) {
            $ran += $engine->fire('card.due_soon', $card);
        }

        return $ran;
    }

    /**
     * Time-driven rules ("every day at 09:00…"): the matcher decides whether
     * the occurrence just crossed; last_run_at (updated by the pipeline) makes
     * each occurrence fire exactly once.
     */
    private function runScheduledRules(AutomationEngine $engine, ScheduleMatcher $matcher, Carbon $now): int
    {
        $ran = 0;

        Automation::query()
            ->where('is_active', true)
            ->where('trigger_type', 'scheduled')
            ->with('creator')
            ->get()
            ->each(function (Automation $automation) use (&$ran, $engine, $matcher, $now) {
                if (! $matcher->isDue($automation->trigger_config ?? [], $now, $automation->last_run_at)) {
                    return;
                }

                $ran += $this->asCreator($automation, fn (): int => $engine->runScheduledRule($automation));
            });

        return $ran;
    }

    /**
     * Due-date rules ("N days before/after the due date"): each run processes
     * the cards whose relative instant (due_at ± N days) fell inside the
     * window since the rule's previous run — every instant belongs to exactly
     * one window, so a card fires once.
     */
    private function runDueRelativeRules(AutomationEngine $engine, Carbon $now): int
    {
        $ran = 0;

        Automation::query()
            ->where('is_active', true)
            ->where('trigger_type', 'card.due_relative')
            ->with('creator')
            ->get()
            ->each(function (Automation $automation) use (&$ran, $engine, $now) {
                $config = $automation->trigger_config ?? [];
                $days = max(0, (int) ($config['days'] ?? 0));
                $offset = ($config['direction'] ?? 'before') === 'after' ? -$days : $days;

                // Capture BEFORE running: the pipeline moves last_run_at forward.
                $windowStart = $automation->last_run_at ?? $automation->created_at;

                // instant = due_at ∓ offset ∈ (windowStart, now]  ⇔  due_at ∈ (ws + offset, now + offset]
                $cards = Card::query()
                    ->where('board_id', $automation->board_id)
                    ->whereNull('archived_at')
                    ->whereNotNull('due_at')
                    ->where('due_at', '>', $windowStart->copy()->addDays($offset))
                    ->where('due_at', '<=', $now->copy()->addDays($offset))
                    ->get();

                if ($cards->isEmpty()) {
                    return;
                }

                $ran += $this->asCreator($automation, function () use ($engine, $automation, $cards): int {
                    $n = 0;

                    foreach ($cards as $card) {
                        $n += $engine->runForCard($automation, $card);
                    }

                    return $n;
                });
            });

        return $ran;
    }

    /**
     * Run one rule under its creator's identity, then restore the previous
     * identity. Butler semantics: scheduled work is attributed to the creator —
     * created cards, comments and notifications carry their identity, and the
     * "by me" actor scope resolves against them. A rule with no creator runs
     * userless, and crucially never inherits the previous rule's identity: the
     * loop processes rules from many creators in one process.
     *
     * @param  \Closure(): int  $callback
     */
    private function asCreator(Automation $automation, \Closure $callback): int
    {
        $previous = Auth::user();

        if ($automation->creator !== null) {
            Auth::setUser($automation->creator);
        } else {
            Auth::forgetUser();
        }

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }
}
