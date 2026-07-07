<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['board_id', 'card_id', 'user_id', 'type', 'source', 'properties'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this activity is a comment (used by the board activity slide-over
     * to filter the "Commentaires" tab).
     */
    public function isComment(): bool
    {
        return str_starts_with($this->type, 'comment.');
    }

    /**
     * A localized, human-readable sentence describing this activity, in the
     * Trello style ("a ajouté [card] à [list]"). The acting user's name is
     * rendered separately, so this returns only the verb phrase.
     */
    public function describe(): string
    {
        $props = $this->properties ?? [];
        $card = $this->card?->title ?? ($props['card_title'] ?? '#'.($props['number'] ?? '?'));

        $type = $this->type;

        // card.moved logged before source lists were tracked (or a same-board
        // reorder) falls back to a shorter phrase without the origin list.
        if ($type === 'card.moved' && empty($props['from_list'])) {
            $type = 'card.moved_simple';
        }

        $replace = [
            'card' => $card,
            'list' => $props['list'] ?? '',
            'from' => $props['from_list'] ?? '',
            'to' => $props['to_list'] ?? '',
            'value' => $props['value'] ?? '',
            'number' => $props['number'] ?? '',
            'user' => $props['user_name'] ?? '',
            'excerpt' => $props['excerpt'] ?? '',
        ];

        $key = 'board_activity.'.$type;

        if (! trans()->has($key)) {
            $key = 'board_activity.default';
        }

        return trans($key, $replace);
    }
}
