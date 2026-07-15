<?php

namespace App\Plugins;

use App\Jobs\WarmPluginList;
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
            // Warm off the web request so a slow plugin API never blocks the page
            // (nor starves PHP-FPM workers). The sync queue (tests) warms inline, so
            // items are ready on the re-read below; a real queue warms in the
            // background and {@see PluginList} polls until the cache is warm.
            $this->queueWarm($list, $limit);
            $cached = Cache::get($key);
        }

        return collect(is_array($cached) ? $cached : [])->map(fn (array $row): PluginListItem => new PluginListItem(
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
     * True while a plugin list's page has no cached value yet — i.e. a warm is
     * (or should be) in flight, so the component shows a skeleton and polls.
     */
    public function isWarming(BoardList $list, int $limit = self::DEFAULT_LIMIT): bool
    {
        return $list->isPluginList() && ! is_array(Cache::get($this->cacheKey($list, $limit)));
    }

    /**
     * Fetch a page from the plugin and cache it. Runs in the queue (via
     * {@see WarmPluginList}) for the automatic first load, or inline on an
     * explicit user refresh.
     */
    public function warm(BoardList $list, int $limit = self::DEFAULT_LIMIT): void
    {
        $items = $this->fetch($list, $limit)->map->toArray()->all();

        Cache::put($this->cacheKey($list, $limit), $items, now()->addMinutes(self::TTL_MINUTES));
        Cache::forget($this->warmingKey($list, $limit));
    }

    /**
     * Drop every cached page for this list and re-warm the current one inline
     * (an explicit user action expects fresh data in the same round-trip).
     */
    public function refresh(BoardList $list, int $limit = self::DEFAULT_LIMIT): void
    {
        for ($page = self::DEFAULT_LIMIT; $page <= 600; $page += self::DEFAULT_LIMIT) {
            Cache::forget($this->cacheKey($list, $page));
            Cache::forget($this->warmingKey($list, $page));
        }

        $this->warm($list, $limit);
    }

    /**
     * Queue a background warm, deduped so only one runs per (list, page) at a
     * time. The lock self-expires, so a failed warm re-queues on the next render.
     */
    private function queueWarm(BoardList $list, int $limit): void
    {
        if (Cache::add($this->warmingKey($list, $limit), true, now()->addSeconds(30))) {
            WarmPluginList::dispatch($list->id, $limit);
        }
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

    private function warmingKey(BoardList $list, int $limit): string
    {
        return $this->cacheKey($list, $limit).':warming';
    }
}
