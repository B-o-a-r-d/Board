<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['custom_field_id', 'card_id', 'value'])]
class CustomFieldValue extends Model
{
    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
