<?php

namespace App\Automations\Contracts;

use App\Models\Card;

/**
 * A condition guards an automation between its trigger and its actions: every
 * condition of a rule must pass (AND) for the pipeline to run. Conditions are
 * code-defined (the library) and registered in the registry.
 */
interface AutomationCondition
{
    /** Stable identifier stored on the automation (e.g. 'has_label'). */
    public static function key(): string;

    /** Human label for the automation builder UI. */
    public function label(): string;

    /**
     * Config fields the condition needs (rendered in the builder).
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function configFields(): array;

    /**
     * Whether the card satisfies this condition.
     *
     * @param  array<string, mixed>  $config  the automation's stored condition config
     */
    public function passes(Card $card, array $config): bool;
}
