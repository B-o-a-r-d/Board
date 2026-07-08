<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace-scoped role definition: a named set of Permissions. System roles
 * (owner/admin/member/observer) are seeded per workspace from the Role enum;
 * admins may add custom roles.
 */
#[Fillable(['workspace_id', 'key', 'name', 'permissions', 'is_system', 'color', 'position'])]
class Role extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($value, $this->permissions ?? [], true);
    }

    /**
     * A role administers when it can manage members (the gate for board/workspace
     * administration abilities).
     */
    public function isAdministrator(): bool
    {
        return $this->hasPermission(Permission::MemberManage);
    }
}
