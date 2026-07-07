<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plugin package installed at runtime from the marketplace (distinct from a
 * per-board `board_plugins` instance). The extracted code lives on a persistent
 * volume at `storage/app/plugins/<key>/` and is booted by PluginLoaderServiceProvider.
 */
#[Fillable(['key', 'name', 'repo', 'version', 'sdk_constraint', 'path', 'enabled', 'installed_by', 'available_version', 'breaking_update', 'load_error'])]
class PluginPackage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'breaking_update' => 'boolean',
        ];
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    /**
     * Whether a newer release is available (set by checkUpdates()).
     */
    public function hasUpdate(): bool
    {
        return $this->available_version !== null && $this->available_version !== $this->version;
    }
}
