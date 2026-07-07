<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'name', 'type', 'options', 'position'])]
class CustomField extends Model
{
    use HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
