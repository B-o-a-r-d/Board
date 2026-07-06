<?php

namespace App\Automations;

use App\Automations\Contracts\AutomationAction;
use App\Automations\Contracts\AutomationTrigger;

/**
 * The code-defined library of available triggers and actions. Populated once
 * (see AppServiceProvider) and resolved as a singleton. Adding a new automation
 * capability = writing a class and registering it here — no data-driven JSON.
 */
class AutomationRegistry
{
    /** @var array<string, AutomationTrigger> */
    private array $triggers = [];

    /** @var array<string, AutomationAction> */
    private array $actions = [];

    public function registerTrigger(AutomationTrigger $trigger): void
    {
        $this->triggers[$trigger::key()] = $trigger;
    }

    public function registerAction(AutomationAction $action): void
    {
        $this->actions[$action::key()] = $action;
    }

    /** @return array<string, AutomationTrigger> */
    public function triggers(): array
    {
        return $this->triggers;
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

    public function action(string $key): ?AutomationAction
    {
        return $this->actions[$key] ?? null;
    }
}
