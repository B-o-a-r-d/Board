<?php

namespace App\Livewire\Workspaces;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rôles et permissions')]
class Roles extends Component
{
    public Workspace $workspace;

    /** The custom role being created/edited (null = the create form is idle). */
    public ?int $editingRoleId = null;

    public bool $showForm = false;

    public string $roleName = '';

    /** @var array<int, string> Permission values. */
    public array $rolePermissions = [];

    public string $roleColor = '#6366f1';

    /** Target workspace (public_id) for the "copy roles" action. */
    public string $copyTargetId = '';

    public function mount(Workspace $workspace): void
    {
        $this->authorize('manageMembers', $workspace);

        $this->workspace = $workspace;
    }

    public function startCreate(): void
    {
        $this->reset('editingRoleId', 'roleName', 'rolePermissions', 'roleColor');
        $this->roleColor = '#6366f1';
        $this->rolePermissions = [Permission::BoardView->value];
        $this->showForm = true;
    }

    public function startEdit(int $roleId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        // System roles are editable too, so admins keep control over the defaults —
        // except Owner, the recovery anchor that always retains every permission.
        $role = $this->workspace->roles()->where('key', '!=', 'owner')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->rolePermissions = $role->permissions ?? [];
        $this->roleColor = $role->color ?? '#6366f1';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->reset('editingRoleId', 'roleName', 'rolePermissions', 'roleColor', 'showForm');
    }

    public function togglePermission(string $permission): void
    {
        if (! in_array($permission, Permission::values(), true)) {
            return;
        }

        $this->rolePermissions = in_array($permission, $this->rolePermissions, true)
            ? array_values(array_diff($this->rolePermissions, [$permission]))
            : [...$this->rolePermissions, $permission];
    }

    public function saveRole(): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $data = $this->validate([
            'roleName' => ['required', 'string', 'max:60'],
            'rolePermissions' => ['array'],
            'rolePermissions.*' => [Rule::in(Permission::values())],
            'roleColor' => ['nullable', 'string', 'max:7'],
        ], attributes: ['roleName' => 'nom']);

        // A role can always at least view the board.
        $permissions = array_values(array_unique([Permission::BoardView->value, ...$data['rolePermissions']]));

        if ($this->editingRoleId !== null) {
            $role = $this->workspace->roles()->where('key', '!=', 'owner')->findOrFail($this->editingRoleId);
            $role->update(['name' => $data['roleName'], 'permissions' => $permissions, 'color' => $data['roleColor'] ?: null]);
        } else {
            $this->workspace->roles()->create([
                'key' => $this->uniqueKey($data['roleName']),
                'name' => $data['roleName'],
                'permissions' => $permissions,
                'is_system' => false,
                'color' => $data['roleColor'] ?: null,
                'position' => (int) $this->workspace->roles()->max('position') + 1,
            ]);
        }

        $this->cancelForm();
        $this->dispatch('toast', message: __('Rôle enregistré'), type: 'success');
    }

    public function deleteRole(int $roleId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $role = $this->workspace->roles()->where('is_system', false)->findOrFail($roleId);

        // Reassign members holding this custom role back to the default Member role.
        $this->workspace->members()->wherePivot('role', $role->key)->get()
            ->each(fn ($member) => $this->workspace->members()->updateExistingPivot($member->id, ['role' => 'member']));

        foreach ($this->workspace->boards as $board) {
            $board->members()->wherePivot('role', $role->key)->get()
                ->each(fn ($member) => $board->members()->updateExistingPivot($member->id, ['role' => 'member']));
        }

        $role->delete();
        $this->dispatch('toast', message: __('Rôle supprimé'), type: 'info');
    }

    /**
     * Clone this workspace's custom roles into another workspace the user
     * administers (system roles already exist there). Existing keys are skipped.
     */
    public function copyRolesTo(): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $target = Workspace::where('public_id', $this->copyTargetId)->firstOrFail();

        abort_unless(Auth::user()->can('manageMembers', $target), 403);

        $existingKeys = $target->roles()->pluck('key')->all();

        foreach ($this->workspace->roles()->where('is_system', false)->get() as $role) {
            if (in_array($role->key, $existingKeys, true)) {
                continue;
            }

            $target->roles()->create([
                'key' => $role->key,
                'name' => $role->name,
                'permissions' => $role->permissions,
                'is_system' => false,
                'color' => $role->color,
                'position' => (int) $target->roles()->max('position') + 1,
            ]);
        }

        $this->copyTargetId = '';
        $this->dispatch('toast', message: __('Rôles copiés'), type: 'success');
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $key = $base;
        $i = 2;

        while ($this->workspace->roles()->where('key', $key)->exists()) {
            $key = $base.'-'.$i++;
        }

        return $key;
    }

    public function render(): View
    {
        return view('livewire.workspaces.roles', [
            'roles' => $this->workspace->roles()->orderBy('position')->get(),
            'permissionGroups' => collect(Permission::cases())->groupBy(fn (Permission $p) => $p->group()),
            'copyTargets' => Auth::user()->workspaces()
                ->where('workspaces.id', '!=', $this->workspace->id)
                ->get()
                ->filter(fn (Workspace $w) => Auth::user()->can('manageMembers', $w))
                ->values(),
        ]);
    }
}
