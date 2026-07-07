<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'related_card_id', 'type'])]
class CardLink extends Model
{
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function relatedCard(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'related_card_id');
    }
}
