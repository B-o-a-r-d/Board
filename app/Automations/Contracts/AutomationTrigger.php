<?php

namespace App\Automations\Contracts;

use App\Models\Card;

/**
 * A trigger describes *when* an automation should run. Triggers are code-defined
 * (the library), registered in the AutomationRegistry; a board stores instances
 * (which trigger + config) in the `automations` table.
 */
interface AutomationTrigger
{
    /** Stable identifier stored on the automation (e.g. 'card.moved_to_list'). */
    public static function key(): string;

    /** Human label for the automation builder UI. */
    public function label(): string;

    /**
     * App events this trigger reacts to (e.g. ['card.moved']).
     *
     * @return array<int, string>
     */
    public function events(): array;

    /**
     * Config fields the trigger needs (rendered in the builder).
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function configFields(): array;

    /**
     * Does the fired event match this trigger's configuration?
     *
     * @param  array<string, mixed>  $config  the automation's stored trigger config
     * @param  array<string, mixed>  $payload  event data
     */
    public function matches(string $event, Card $card, array $config, array $payload): bool;
}
