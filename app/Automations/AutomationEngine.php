<?php

namespace App\Automations;

use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Card;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Evaluates a board's automations against a fired event and runs the matching
 * action pipelines. Actions mutate models directly (they never re-enter the
 * engine), so a single event cannot cascade into an automation loop.
 */
class AutomationEngine
{
    /** Hard cap per rule execution — a runaway pipeline can't stall a request. */
    public const MAX_ACTIONS = 10;

    /** Consecutive fully-failed runs before a rule is auto-quarantined. */
    public const MAX_CONSECUTIVE_FAILURES = 10;

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
        $errors = [];

        foreach (array_slice($automation->actionList(), 0, self::MAX_ACTIONS) as $step) {
            $action = $this->registry->action($step['type']);

            if ($action === null) {
                $failed++;
                $errors[] = "action inconnue: {$step['type']}";

                continue;
            }

            try {
                $action->run($card, $step['config']);
                $executed++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $errors[] = "{$step['type']}: ".$this->safeErrorMessage($e);
            }
        }

        // A fully-failed run (nothing ran) advances the quarantine counter; any
        // successful action means the rule still does something → reset.
        $fullyFailed = $executed === 0 && $failed > 0;
        $consecutive = $fullyFailed ? $automation->consecutive_failures + 1 : 0;

        $automation->forceFill([
            'last_run_at' => now(),
            'runs_count' => $automation->runs_count + 1,
            'failures_count' => $automation->failures_count + $failed,
            'consecutive_failures' => $consecutive,
        ])->save();

        AutomationRun::create([
            'automation_id' => $automation->id,
            'board_id' => $automation->board_id,
            'card_id' => $card->exists ? $card->id : null,
            'status' => $failed === 0 ? 'success' : ($executed > 0 ? 'partial' : 'failed'),
            'actions_run' => $executed,
            'actions_failed' => $failed,
            'error' => $errors === [] ? null : Str::limit(implode(' · ', $errors), 1000),
        ]);

        if ($consecutive >= self::MAX_CONSECUTIVE_FAILURES && $automation->is_active) {
            $this->quarantine($automation, $card);
        }

        return $executed;
    }

    /**
     * Error text safe to persist in the (admin-visible) run journal. A
     * RequestException carries a slice of the remote response body in its
     * message, so for those we keep only the status and host — the full
     * exception still reaches the application log via report().
     */
    private function safeErrorMessage(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $host = $e->response->effectiveUri()?->getHost() ?? 'remote';

            return "HTTP {$e->response->status()} depuis {$host}";
        }

        return $e->getMessage();
    }

    /**
     * Disable a rule that keeps failing and surface it in the board activity
     * (which admins see, and which broadcasts live) — the same fail-safe idea
     * as the plugin quarantine.
     */
    private function quarantine(Automation $automation, Card $card): void
    {
        $automation->forceFill(['is_active' => false])->save();

        Activity::create([
            'board_id' => $automation->board_id,
            'card_id' => $card->exists ? $card->id : null,
            'type' => 'automation.disabled',
            'properties' => ['automation' => $automation->name],
        ]);

        broadcast(new BoardActivity($automation->board_id, 'automations.changed'));
    }
}
