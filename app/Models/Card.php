<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_list_id', 'board_id', 'created_by', 'title', 'description', 'position', 'cover_path', 'cover_color', 'due_at', 'completed_at', 'archived_at'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }
}
