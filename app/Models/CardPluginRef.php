<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An external reference (a GitHub commit / PR / issue…) attached to a card by a
 * plugin. The resolved widget is snapshotted in `payload` so the card renders
 * without re-hitting the external API on every view.
 */
#[Fillable(['card_id', 'board_plugin_id', 'plugin_key', 'ref_type', 'ref_id', 'payload', 'created_by'])]
class CardPluginRef extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function boardPlugin(): BelongsTo
    {
        return $this->belongsTo(BoardPlugin::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
