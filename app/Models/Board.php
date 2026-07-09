<?php

namespace App\Models;

use App\Enums\BoardVisibility;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Concerns\HasPublicId;
use App\Models\Role as RoleModel;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'description', 'background', 'background_image', 'visibility', 'is_template', 'share_token', 'ical_token', 'position', 'archived_at'])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory, HasPublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => BoardVisibility::class,
            'archived_at' => 'datetime',
            'is_template' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Board>  $query
     */
    public function scopeTemplates(Builder $query): void
    {
        $query->where('is_template', true)->whereNull('archived_at');
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    public function enableSharing(): void
    {
        if ($this->share_token === null) {
            $this->update(['share_token' => Str::random(32)]);
        }
    }

    public function disableSharing(): void
    {
        $this->update(['share_token' => null]);
    }

    public function hasIcalFeed(): bool
    {
        return $this->ical_token !== null;
    }

    public function enableIcalFeed(): void
    {
        if ($this->ical_token === null) {
            $this->update(['ical_token' => Str::random(40)]);
        }
    }

    public function regenerateIcalFeed(): void
    {
        $this->update(['ical_token' => Str::random(40)]);
    }

    public function disableIcalFeed(): void
    {
        $this->update(['ical_token' => null]);
    }

    public function icalUrl(): ?string
    {
        return $this->ical_token ? route('boards.ical', ['token' => $this->ical_token]) : null;
    }

    /**
     * CSS background value for the board surface: an uploaded image takes
     * precedence over a gradient preset; null means no custom background.
     */
    public function backgroundStyle(): ?string
    {
        if ($this->background_image) {
            return "url('".Storage::disk('public')->url($this->background_image)."') center / cover no-repeat";
        }

        if ($this->background) {
            return config('board.backgrounds')[$this->background] ?? null;
        }

        return null;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** Cards mirrored onto this board from anywhere (the same underlying cards). */
    public function mirrors(): HasMany
    {
        return $this->hasMany(CardMirror::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(BoardView::class);
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('position');
    }

    public function plugins(): HasMany
    {
        return $this->hasMany(BoardPlugin::class);
    }

    /**
     * @param  Builder<Board>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function memberRole(User $user): ?Role
    {
        $membership = $this->members()->whereKey($user->getKey())->first();

        return $membership ? Role::tryFrom($membership->pivot->role) : null;
    }

    /**
     * The board role definition for a user (resolved by their board pivot key
     * against the workspace's role set), or null when they are not a board member.
     */
    public function roleFor(User $user): ?RoleModel
    {
        $membership = $this->members()->whereKey($user->getKey())->first();

        return $membership ? $this->workspace->roles()->where('key', $membership->pivot->role)->first() : null;
    }

    /**
     * Whether a user holds a permission on this board. Workspace administrators
     * may do anything on any of their boards; otherwise the board role decides.
     */
    public function userCan(User $user, Permission $permission): bool
    {
        // A deactivated workspace member loses access to every board it owns,
        // regardless of any board-level role they may still hold.
        if ($this->workspace->memberIsDeactivated($user)) {
            return false;
        }

        if ($this->workspace->memberRole($user)?->isAdministrator()) {
            return true;
        }

        return (bool) $this->roleFor($user)?->hasPermission($permission);
    }
}
