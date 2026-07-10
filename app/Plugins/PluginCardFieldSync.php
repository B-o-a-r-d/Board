<?php

namespace App\Plugins;

use App\Enums\CustomFieldType;
use App\Models\BoardPlugin;
use Board\PluginSdk\Contracts\ProvidesCardFields;
use Board\PluginSdk\PluginRegistry;

/**
 * Materializes the custom fields a Power-Up declares ({@see ProvidesCardFields})
 * as managed rows in `custom_fields` (marked with `plugin_key` + `field_key`).
 * Synced on install/activation, removed on uninstall; values ride the existing
 * custom-field machinery (validation, rendering, badges) for free.
 */
class PluginCardFieldSync
{
    public function __construct(private readonly PluginRegistry $registry) {}

    /**
     * Upsert the plugin's declared fields on the instance's board and drop the
     * declarations that disappeared. No-op for plugins without the capability.
     */
    public function sync(BoardPlugin $instance): void
    {
        $plugin = $this->registry->get($instance->plugin_key);

        if (! $plugin instanceof ProvidesCardFields) {
            return;
        }

        try {
            $declarations = $plugin->cardFields($instance->config ?? []);
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        $board = $instance->board;
        $position = (int) $board->customFields()->max('position');
        $keptKeys = [];

        foreach ($declarations as $declaration) {
            $key = mb_substr(trim((string) ($declaration['key'] ?? '')), 0, 60);
            $name = trim((string) ($declaration['name'] ?? ''));

            if ($key === '' || $name === '') {
                continue;
            }

            $keptKeys[] = $key;

            $type = CustomFieldType::tryFrom((string) ($declaration['type'] ?? 'text')) ?? CustomFieldType::Text;
            $placement = ($declaration['placement'] ?? '') === 'content' ? 'content' : 'sidebar';
            $options = is_array($declaration['options'] ?? null) ? $declaration['options'] : null;

            $board->customFields()->updateOrCreate(
                ['plugin_key' => $instance->plugin_key, 'field_key' => $key],
                [
                    'name' => mb_substr($name, 0, 60),
                    'type' => $type,
                    'options' => $options,
                    'placement' => $placement,
                    'position' => $board->customFields()
                        ->where('plugin_key', $instance->plugin_key)
                        ->where('field_key', $key)
                        ->value('position') ?? ++$position,
                ],
            );
        }

        // Declarations the plugin no longer ships (values cascade with the row).
        $board->customFields()
            ->where('plugin_key', $instance->plugin_key)
            ->when($keptKeys !== [], fn ($q) => $q->whereNotIn('field_key', $keptKeys))
            ->delete();
    }

    /**
     * Remove every field this plugin manages on the instance's board.
     */
    public function remove(BoardPlugin $instance): void
    {
        $instance->board->customFields()->where('plugin_key', $instance->plugin_key)->delete();
    }
}
