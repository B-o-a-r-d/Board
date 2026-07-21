<?php

namespace App\Plugins;

use App\Models\Board;
use Board\PluginSdk\Contracts\ProvidesBoardType;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\Route;

/**
 * Board types contributed by active plugins ({@see ProvidesBoardType}).
 * The host's own 'kanban' type is implicit and never listed here — a type is
 * only offered while its plugin is loaded, and a board whose type has no
 * loaded plugin stays visible but unopenable ("Power-Up requis").
 */
class BoardTypes
{
    public function __construct(private readonly PluginRegistry $registry) {}

    /**
     * @return array<string, array{key: string, label: string, icon: string, route: string}>
     */
    public function all(): array
    {
        $types = [];

        foreach ($this->registry->all() as $plugin) {
            if (! $plugin instanceof ProvidesBoardType) {
                continue;
            }

            $key = $plugin->boardTypeKey();

            // 'kanban' is reserved by the host; a route that does not exist
            // would break every dashboard render.
            if ($key === Board::TYPE_KANBAN || ! Route::has($plugin->boardTypeRoute())) {
                continue;
            }

            $types[$key] = [
                'key' => $key,
                'label' => $plugin->boardTypeLabel(),
                'icon' => $plugin->boardTypeIcon(),
                'route' => $plugin->boardTypeRoute(),
            ];
        }

        return $types;
    }

    /**
     * @return array{key: string, label: string, icon: string, route: string}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }
}
