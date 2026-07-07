<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An installed plugin instance on a board (a "Power-Up"). The `config` holds
 * whatever the plugin needs — including OAuth tokens — and is **encrypted at
 * rest** (first use of `encrypted:array` in the app).
 */
#[Fillable(['board_id', 'plugin_key', 'name', 'config', 'is_active'])]
class BoardPlugin extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function sourcedLists(): HasMany
    {
        return $this->hasMany(BoardList::class, 'source_plugin_id');
    }

    /**
     * Whether an OAuth connection has been completed for this instance.
     */
    public function isConnected(): bool
    {
        return ! empty(($this->config ?? [])['token']);
    }
}
