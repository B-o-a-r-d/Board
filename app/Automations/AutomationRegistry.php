<?php

namespace App\Automations;

use App\Automations\Contracts\AutomationAction;
use App\Automations\Contracts\AutomationCondition;
use App\Automations\Contracts\AutomationTrigger;

/**
 * The code-defined library of available triggers, conditions and actions.
 * Populated once (see AppServiceProvider) and resolved as a singleton. Adding
 * a new automation capability = writing a class and registering it here — no
 * data-driven JSON.
 */
class AutomationRegistry
{
    /** @var array<string, AutomationTrigger> */
    private array $triggers = [];

    /** @var array<string, AutomationCondition> */
    private array $conditions = [];

    /** @var array<string, AutomationAction> */
    private array $actions = [];

    public function registerTrigger(AutomationTrigger $trigger): void
    {
        $this->triggers[$trigger::key()] = $trigger;
    }

    public function registerCondition(AutomationCondition $condition): void
    {
        $this->conditions[$condition::key()] = $condition;
    }

    /**
     * Core actions register under their static key; plugin-contributed actions
     * pass an explicit qualified key ("plugin:<plugin>:<action>") since one
     * adapter class wraps many dynamic actions.
     */
    public function registerAction(AutomationAction $action, ?string $key = null): void
    {
        $this->actions[$key ?? $action::key()] = $action;
    }

    /** @return array<string, AutomationTrigger> */
    public function triggers(): array
    {
        return $this->triggers;
    }

    /** @return array<string, AutomationCondition> */
    public function conditions(): array
    {
        return $this->conditions;
    }

    /** @return array<string, AutomationAction> */
    public function actions(): array
    {
        return $this->actions;
    }

    public function trigger(string $key): ?AutomationTrigger
    {
        return $this->triggers[$key] ?? null;
    }

    public function condition(string $key): ?AutomationCondition
    {
        return $this->conditions[$key] ?? null;
    }

    public function action(string $key): ?AutomationAction
    {
        return $this->actions[$key] ?? null;
    }
}
