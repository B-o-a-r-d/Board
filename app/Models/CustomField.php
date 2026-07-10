<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'board_list_id', 'card_id', 'plugin_key', 'field_key', 'name', 'type', 'options', 'placement', 'position'])]
class CustomField extends Model
{
    use HasPublicId;

    public const PLACEMENT_SIDEBAR = 'sidebar';

    public const PLACEMENT_CONTENT = 'content';

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

    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Whether this field shows on the given card: board-wide fields apply
     * everywhere, list-scoped ones to the cards of that list, card-scoped
     * ones to that single card.
     */
    public function appliesToCard(Card $card): bool
    {
        return match (true) {
            $this->card_id !== null => $this->card_id === $card->id,
            $this->board_list_id !== null => $this->board_list_id === $card->board_list_id,
            default => true,
        };
    }

    /**
     * Fields applicable to a card (see {@see appliesToCard()}), as a query.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForCard(Builder $query, Card $card): void
    {
        $query->where(function (Builder $q) use ($card) {
            $q->where(fn (Builder $w) => $w->whereNull('card_id')->whereNull('board_list_id'))
                ->orWhere('board_list_id', $card->board_list_id)
                ->orWhere('card_id', $card->id);
        });
    }

    /**
     * Whether this field is managed by a Power-Up (synced, not user-deletable).
     */
    public function isPluginManaged(): bool
    {
        return $this->plugin_key !== null;
    }

    /**
     * Fields visible on a board's cards: user fields plus fields owned by a
     * plugin instance that is installed AND active on that board.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleOn(Builder $query, Board $board): void
    {
        $query->where(function (Builder $q) use ($board) {
            $q->whereNull('plugin_key')
                ->orWhereIn('plugin_key', $board->plugins()->where('is_active', true)->select('plugin_key'));
        });
    }

    /**
     * Decode a raw stored value according to this field's type (JSON types
     * come back as arrays, everything else as the raw string).
     */
    public function decode(?string $raw): mixed
    {
        if ($raw === null || ! $this->type->storesJson()) {
            return $raw;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The list of selectable options (Select / MultiSelect), ignoring any
     * associative config keys other types keep in `options` (e.g. currency).
     *
     * @return array<int, string>
     */
    public function optionList(): array
    {
        return array_values(array_filter(
            $this->options ?? [],
            fn ($value, $key): bool => is_int($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Currency symbol for Money fields (stored in options).
     */
    public function currency(): string
    {
        return (string) (($this->options ?? [])['currency'] ?? '€');
    }
}
