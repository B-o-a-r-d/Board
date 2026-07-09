<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A placement of an existing card into another list/board. It is NOT a copy: the
 * mirror renders (and links to) the same underlying card; editing the card is
 * reflected everywhere it is mirrored.
 */
#[Fillable(['card_id', 'board_list_id', 'board_id', 'created_by', 'position'])]
class CardMirror extends Model
{
    /** The source card being mirrored. */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** The list this mirror appears in. */
    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    /** The board this mirror appears on. */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }
}
