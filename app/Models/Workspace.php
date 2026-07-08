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

#[Fillable(['owner_id', 'name', 'slug', 'description', 'color'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasPublicId;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
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
        $membership = $this->members()->whereKey($user->getKey())->first();

        return $membership ? $this->roles()->where('key', $membership->pivot->role)->first() : null;
    }

    public function userCan(User $user, Permission $permission): bool
    {
        return (bool) $this->roleFor($user)?->hasPermission($permission);
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
        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function memberRole(User $user): ?Role
    {
        $membership = $this->members()->whereKey($user->getKey())->first();

        return $membership ? Role::tryFrom($membership->pivot->role) : null;
    }
}
