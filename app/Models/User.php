<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'locale', 'is_admin', 'notification_preferences', 'avatar_path', 'biography', 'ical_token'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'ical_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable, TwoFactorAuthenticatable;

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Boards this user has pinned for quick access on the dashboard, regardless
     * of workspace. Pinning is independent of membership (via the `board_pins`
     * pivot).
     */
    public function pinnedBoards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_pins')->withTimestamps();
    }

    public function assignedCards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class)->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
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
        return $this->ical_token ? route('calendar.ical', ['token' => $this->ical_token]) : null;
    }

    /**
     * Public URL of the uploaded avatar, or null when the user has none
     * (callers fall back to initials via the <x-user-avatar> component).
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? route('media.avatar', $this)
            : null;
    }

    /**
     * Default notification preferences (channels + per-event toggles).
     *
     * @return array<string, bool>
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'inapp' => true,
            'email' => false,
            'comments' => true,
            'mentions' => true,
            'reactions' => true,
            'assignments' => true,
            'mentions_only' => false,
        ];
    }

    /**
     * The user's notification preferences merged over the defaults.
     *
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        return array_merge(self::defaultNotificationPreferences(), $this->notification_preferences ?? []);
    }
}
