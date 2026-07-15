<?php

namespace App\Automations;

use App\Automations\Contracts\AutomationAction;
use App\Events\UserToast;
use App\Models\BoardPlugin;
use App\Models\Card;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesAutomationActions;
use Illuminate\Support\Facades\Auth;

/**
 * Adapts one action declared by a Power-Up ({@see ProvidesAutomationActions})
 * to the host's AutomationAction contract. Registered under the qualified key
 * "plugin:<plugin>:<action>"; at run time it resolves the board's ACTIVE
 * plugin instance and hands the plugin a normalized card snapshot — a missing
 * or inactive instance throws, which the pipeline sandbox counts as a failure
 * without blocking the rule's other actions.
 */
class PluginAutomationAction implements AutomationAction
{
    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $fields
     */
    public function __construct(
        private readonly Plugin&ProvidesAutomationActions $plugin,
        private readonly string $actionKey,
        private readonly string $actionLabel,
        private readonly array $fields = [],
    ) {}

    /** Unused for plugin actions — they register under {@see qualifiedKey()}. */
    public static function key(): string
    {
        return 'plugin';
    }

    public function qualifiedKey(): string
    {
        return 'plugin:'.$this->plugin::key().':'.$this->actionKey;
    }

    public function pluginKey(): string
    {
        return $this->plugin::key();
    }

    public function label(): string
    {
        return $this->actionLabel;
    }

    public function configFields(): array
    {
        return $this->fields;
    }

    public function run(Card $card, array $config): void
    {
        $instance = BoardPlugin::query()
            ->where('board_id', $card->board_id)
            ->where('plugin_key', $this->plugin::key())
            ->where('is_active', true)
            ->first();

        if ($instance === null) {
            throw new \RuntimeException("Power-Up '{$this->plugin::key()}' is not installed or active on this board.");
        }

        $toast = $this->plugin->runAutomationAction(
            $instance->config ?? [],
            $this->actionKey,
            [
                // Nulls for board-scope (cardless) runs — documented in the SDK.
                'id' => $card->public_id,
                'title' => $card->title,
                'list' => $card->list?->name,
                'board' => $card->board->name,
                'due_at' => $card->due_at?->toIso8601String(),
                'completed_at' => $card->completed_at?->toIso8601String(),
            ],
            $config,
        );

        // Surface the outcome to whoever caused the run (scheduled rules run as
        // the rule's creator). No actor — e.g. a system-fired event — no toast.
        if ($toast !== null && ($userId = Auth::id()) !== null) {
            UserToast::dispatch(
                (int) $userId,
                $toast->message,
                $toast->description,
                $toast->type,
                $toast->duration,
                $toast->actions,
            );
        }
    }
}
