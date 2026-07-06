<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\Card;

/**
 * Evaluates a board's automations against a fired event and runs the matching
 * actions. Actions mutate models directly (they never re-enter the engine), so
 * a single event cannot cascade into an automation loop.
 */
class AutomationEngine
{
    public function __construct(private AutomationRegistry $registry) {}

    /**
     * Fire an app event for a card and run every matching automation.
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

            if ($this->runAction($automation, $card)) {
                $ran++;
            }
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

        return $this->runAction($automation, $card);
    }

    private function runAction(Automation $automation, Card $card): bool
    {
        $action = $this->registry->action($automation->action_type);

        if ($action === null) {
            return false;
        }

        $action->run($card, $automation->action_config ?? []);

        return true;
    }
}
