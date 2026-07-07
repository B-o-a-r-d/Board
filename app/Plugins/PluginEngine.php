<?php

namespace App\Plugins;

use App\Models\BoardList;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\PluginListItem;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * App-side glue between board lists and the SDK plugin registry. Resolves the
 * plugin that backs a "plugin list", fetches its read-only items (cached), and
 * can bust that cache on demand (manual refresh / webhook / schedule).
 */
class PluginEngine
{
    private const TTL_MINUTES = 5;

    public const DEFAULT_LIMIT = 15;

    public function __construct(private readonly PluginRegistry $registry) {}

    /**
     * Cached items for a plugin list (empty for a normal list). `$limit` is the
     * current page size driven by the list's infinite scroll.
     *
     * Only plain arrays are cached (never live objects) so the value survives
     * any cache driver's serialization; the DTOs are rehydrated on read.
     *
     * @return Collection<int, PluginListItem>
     */
    public function listItems(BoardList $list, int $limit = self::DEFAULT_LIMIT): Collection
    {
        if (! $list->isPluginList()) {
            return collect();
        }

        $key = $this->cacheKey($list, $limit);
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            $cached = $this->fetch($list, $limit)->map->toArray()->all();
            Cache::put($key, $cached, now()->addMinutes(self::TTL_MINUTES));
        }

        return collect($cached)->map(fn (array $row): PluginListItem => new PluginListItem(
            externalRef: (string) ($row['external_ref'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            subtitle: $row['subtitle'] ?? null,
            url: $row['url'] ?? null,
            badge: $row['badge'] ?? null,
            badgeColor: $row['badge_color'] ?? null,
            icon: $row['icon'] ?? null,
            timestamp: $row['timestamp'] ?? null,
        ));
    }

    /**
     * Drop every cached page for this list and re-warm the current one.
     */
    public function refresh(BoardList $list, int $limit = self::DEFAULT_LIMIT): void
    {
        for ($page = self::DEFAULT_LIMIT; $page <= 600; $page += self::DEFAULT_LIMIT) {
            Cache::forget($this->cacheKey($list, $page));
        }

        $this->listItems($list, $limit);
    }

    /**
     * @return Collection<int, PluginListItem>
     */
    private function fetch(BoardList $list, int $limit): Collection
    {
        $instance = $list->sourcePlugin;

        if ($instance === null || ! $instance->is_active) {
            return collect();
        }

        $plugin = $this->registry->get($instance->plugin_key);

        if (! $plugin instanceof ProvidesListSource) {
            return collect();
        }

        try {
            return $plugin->items(
                $instance->config ?? [],
                (string) $list->source_mode,
                array_merge($list->source_config ?? [], ['limit' => $limit]),
            );
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function cacheKey(BoardList $list, int $limit): string
    {
        return "plugin-list:{$list->id}:{$limit}";
    }
}
