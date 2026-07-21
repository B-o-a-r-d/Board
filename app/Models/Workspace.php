<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Concerns\HasPublicId;
use App\Models\Role as RoleModel;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['owner_id', 'name', 'slug', 'type', 'description', 'color', 'allowed_invite_domains', 'allowed_attachment_extensions'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasPublicId;

    /** The historical boards workspace type ('type' column default). */
    public const TYPE_KANBAN = 'kanban';

    /**
     * Whether this is a classic kanban workspace (boards grid). Anything else
     * is a plugin-contributed type routed to the plugin's own page.
     */
    public function isKanban(): bool
    {
        return ($this->type ?? self::TYPE_KANBAN) === self::TYPE_KANBAN;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_invite_domains' => 'array',
            'allowed_attachment_extensions' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role', 'deactivated_at')
            ->withTimestamps();
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleModel::class);
    }

    /**
     * Seed the four system roles from the Role enum. Idempotent.
     */
    public function seedDefaultRoles(): void
    {
        foreach (Role::cases() as $position => $roleKey) {
            $this->roles()->firstOrCreate(
                ['key' => $roleKey->value],
                [
                    'name' => $roleKey->label(),
                    'permissions' => array_map(fn (Permission $permission): string => $permission->value, $roleKey->permissions()),
                    'is_system' => true,
                    'position' => $position,
                ],
            );
        }
    }

    /**
     * The workspace role definition for a member (resolved by their pivot key),
     * or null when they are not a member.
     */
    public function roleFor(User $user): ?RoleModel
    {
        $membership = $this->members()->wherePivotNull('deactivated_at')->whereKey($user->getKey())->first();

        return $membership ? $this->roles()->where('key', $membership->pivot->role)->first() : null;
    }

    public function userCan(User $user, Permission $permission): bool
    {
        return (bool) $this->roleFor($user)?->hasPermission($permission);
    }

    /**
     * Whether this user is a member but has been deactivated — access to the
     * workspace and all its boards is suspended until they are reactivated.
     */
    public function memberIsDeactivated(User $user): bool
    {
        return $this->members()
            ->whereKey($user->getKey())
            ->wherePivotNotNull('deactivated_at')
            ->exists();
    }

    /**
     * Whether an email may be invited given the workspace's domain allow-list.
     * An empty/absent list allows any domain.
     */
    public function invitationDomainAllowed(string $email): bool
    {
        $domains = array_filter((array) $this->allowed_invite_domains);

        if ($domains === []) {
            return true;
        }

        $domain = Str::lower(Str::afterLast($email, '@'));

        return in_array($domain, array_map(fn (string $d): string => Str::lower(ltrim(trim($d), '@')), $domains), true);
    }

    /**
     * Whether an attachment extension is permitted given the workspace's
     * allow-list. An empty/absent list allows any type.
     */
    public function attachmentExtensionAllowed(string $extension): bool
    {
        $allowed = array_filter((array) $this->allowed_attachment_extensions);

        if ($allowed === []) {
            return true;
        }

        $extension = Str::lower(ltrim($extension, '.'));

        return in_array($extension, array_map(fn (string $e): string => Str::lower(ltrim(trim($e), '.')), $allowed), true);
    }

    protected static function booted(): void
    {
        static::created(fn (self $workspace) => $workspace->seedDefaultRoles());
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->wherePivotNull('deactivated_at')->whereKey($user->getKey())->exists();
    }

    public function memberRole(User $user): ?Role
    {
        $membership = $this->members()->wherePivotNull('deactivated_at')->whereKey($user->getKey())->first();

        return $membership ? Role::tryFrom($membership->pivot->role) : null;
    }
}
