<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

/**
 * Evaluates a board's automations against a fired event and runs the matching
 * action pipelines. Actions mutate models directly (they never re-enter the
 * engine), so a single event cannot cascade into an automation loop.
 */
class AutomationEngine
{
    /** Hard cap per rule execution — a runaway pipeline can't stall a request. */
    public const MAX_ACTIONS = 10;

    public function __construct(private AutomationRegistry $registry) {}

    /**
     * Fire an app event for a card and run every matching automation: trigger
     * match → actor scope ("by me") → AND-combined conditions → action pipeline.
     *
     * @param  array<string, mixed>  $payload
     * @return int number of actions actually run (lets callers decide whether to re-render)
     */
    public function fire(string $event, Card $card, array $payload = []): int
    {
        $ran = 0;

        $automations = Automation::query()
            ->where('board_id', $card->board_id)
            ->where('is_active', true)
            ->get();

        foreach ($automations as $automation) {
            $trigger = $this->registry->trigger($automation->trigger_type);

            if ($trigger === null || ! in_array($event, $trigger->events(), true)) {
                continue;
            }

            if (! $trigger->matches($event, $card, $automation->trigger_config ?? [], $payload)) {
                continue;
            }

            if (! $automation->actorAllowed(Auth::id())) {
                continue;
            }

            if (! $this->conditionsPass($automation, $card)) {
                continue;
            }

            $ran += $this->runPipeline($automation, $card);
        }

        return $ran;
    }

    /**
     * Run a manual-trigger automation (a card button) on demand.
     */
    public function runManual(Automation $automation, Card $card): bool
    {
        if ($automation->trigger_type !== 'manual' || ! $automation->is_active) {
            return false;
        }

        if (! $this->conditionsPass($automation, $card)) {
            return false;
        }

        return $this->runPipeline($automation, $card) > 0;
    }

    /**
     * Run a time-driven (scheduled) rule. There is no triggering card: the
     * pipeline receives a phantom, unsaved card carrying only the board
     * context, so board-scope actions (create_card, sort_list,
     * archive_list_cards…) resolve their explicit list configs while
     * card-mutating actions no-op or fail safely into failures_count.
     * Conditions are card-based and therefore skipped for scheduled rules.
     */
    public function runScheduledRule(Automation $automation): int
    {
        return $this->runPipeline($automation, $this->phantomCard($automation));
    }

    /**
     * Run a board button (topbar) on demand — same board-scope semantics as a
     * scheduled rule: a phantom card carries the board context.
     */
    public function runBoardButton(Automation $automation): bool
    {
        if ($automation->trigger_type !== 'board_button' || ! $automation->is_active) {
            return false;
        }

        return $this->runPipeline($automation, $this->phantomCard($automation)) > 0;
    }

    private function phantomCard(Automation $automation): Card
    {
        $card = new Card(['board_id' => $automation->board_id]);
        $card->setRelation('board', $automation->board);

        return $card;
    }

    /**
     * Run a rule against a specific card outside the event flow (the due-date
     * rules). Actor scope and conditions apply as usual.
     */
    public function runForCard(Automation $automation, Card $card): int
    {
        if (! $automation->actorAllowed(Auth::id())) {
            return 0;
        }

        if (! $this->conditionsPass($automation, $card)) {
            return 0;
        }

        return $this->runPipeline($automation, $card);
    }

    /**
     * Every condition of the rule must pass (AND). A rule referencing an
     * unknown condition fails closed: better a silent skip than a rule firing
     * without the guard its author configured.
     */
    private function conditionsPass(Automation $automation, Card $card): bool
    {
        foreach ($automation->conditionList() as $entry) {
            $condition = $this->registry->condition($entry['type']);

            if ($condition === null || ! $condition->passes($card, $entry['config'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute the rule's ordered action pipeline. A failing action is reported
     * and counted but never interrupts the remaining steps.
     *
     * @return int number of actions that ran successfully
     */
    private function runPipeline(Automation $automation, Card $card): int
    {
        $executed = 0;
        $failed = 0;

        foreach (array_slice($automation->actionList(), 0, self::MAX_ACTIONS) as $step) {
            $action = $this->registry->action($step['type']);

            if ($action === null) {
                $failed++;

                continue;
            }

            try {
                $action->run($card, $step['config']);
                $executed++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        $automation->forceFill([
            'last_run_at' => now(),
            'runs_count' => $automation->runs_count + 1,
            'failures_count' => $automation->failures_count + $failed,
        ])->save();

        return $executed;
    }
}
