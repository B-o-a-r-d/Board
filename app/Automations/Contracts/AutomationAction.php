<?php

namespace App\Automations\Contracts;

use App\Models\Card;

/**
 * An action describes *what* an automation does when its trigger matches.
 * Actions are code-defined (the library) and registered in the registry.
 */
interface AutomationAction
{
    /** Stable identifier stored on the automation (e.g. 'assign_label'). */
    public static function key(): string;

    /** Human label for the automation builder UI. */
    public function label(): string;

    /**
     * Config fields the action needs (rendered in the builder).
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function configFields(): array;

    /**
     * Execute the action against a card.
     *
     * @param  array<string, mixed>  $config  the automation's stored action config
     */
    public function run(Card $card, array $config): void;
}
