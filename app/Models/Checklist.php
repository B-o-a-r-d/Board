<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['card_id', 'title', 'position'])]
class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory, HasPublicId;

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function items(): HasMany
    {
        // The id tiebreaker keeps the order stable when positions are tied —
        // without it, updating a row (e.g. checking an item) reshuffled the
        // list, since Postgres gives tied rows no deterministic order.
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }
}
