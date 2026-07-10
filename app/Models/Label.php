<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['board_id', 'name', 'color'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory, HasPublicId;

    /**
     * Validation rule for a hex color (#rrggbb, optionally with an alpha byte).
     * Shared by every label/cover color entry point (Livewire, API, MCP) so a
     * value reflected into an inline `style` attribute cannot carry arbitrary
     * CSS. Use inside a rule array: `['required', 'string', Label::COLOR_RULE]`.
     */
    public const COLOR_RULE = 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/';

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class);
    }
}
