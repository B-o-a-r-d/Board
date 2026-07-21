<?php

namespace App\Plugins;

use App\Models\Workspace;
use Board\PluginSdk\Contracts\ProvidesWorkspaceType;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\Route;

/**
 * Workspace types contributed by active plugins ({@see ProvidesWorkspaceType}).
 * The host's own 'kanban' type is implicit and never listed here — a type is
 * only offered while its plugin is loaded, and a workspace whose type has no
 * loaded plugin stays visible but unopenable ("Power-Up requis").
 */
class WorkspaceTypes
{
    public function __construct(private readonly PluginRegistry $registry) {}

    /**
     * @return array<string, array{key: string, label: string, icon: string, route: string}>
     */
    public function all(): array
    {
        $types = [];

        foreach ($this->registry->all() as $plugin) {
            if (! $plugin instanceof ProvidesWorkspaceType) {
                continue;
            }

            $key = $plugin->workspaceTypeKey();

            // 'kanban' is reserved by the host; a route that does not exist
            // would break every dashboard render.
            if ($key === Workspace::TYPE_KANBAN || ! Route::has($plugin->workspaceTypeRoute())) {
                continue;
            }

            $types[$key] = [
                'key' => $key,
                'label' => $plugin->workspaceTypeLabel(),
                'icon' => $plugin->workspaceTypeIcon(),
                'route' => $plugin->workspaceTypeRoute(),
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
