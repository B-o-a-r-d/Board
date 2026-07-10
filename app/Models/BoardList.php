<?php

namespace App\Models;

use App\Http\Controllers\MediaController;
use App\Models\Concerns\HasPublicId;
use Database\Factories\BoardListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'name', 'cover_color', 'cover_path', 'wip_limit', 'position', 'archived_at', 'source_plugin_id', 'source_mode', 'source_config'])]
class BoardList extends Model
{
    /** @use HasFactory<BoardListFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'wip_limit' => 'integer',
            'source_config' => 'array',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Authorized URL of the uploaded cover image, or null when unset. Served
     * through {@see MediaController} from a private disk.
     * On the public share page pass the board share token for guest access.
     */
    public function coverUrl(?string $shareToken = null): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        $params = [$this];

        if ($shareToken !== null) {
            $params['t'] = $shareToken;
        }

        return route('media.list-cover', $params);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }

    /** Cards mirrored INTO this list from elsewhere (the same underlying cards). */
    public function mirrors(): HasMany
    {
        return $this->hasMany(CardMirror::class)->orderBy('position');
    }

    public function sourcePlugin(): BelongsTo
    {
        return $this->belongsTo(BoardPlugin::class, 'source_plugin_id');
    }

    /**
     * A plugin-sourced list renders read-only virtual cards instead of stored
     * cards (no manual add, no drag & drop).
     */
    public function isPluginList(): bool
    {
        return $this->source_plugin_id !== null;
    }
}
