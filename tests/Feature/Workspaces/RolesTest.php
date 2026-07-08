<?php

use App\Enums\Permission;
use App\Livewire\Boards\Show;
use App\Livewire\Workspaces\Roles;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/**
 * @return array{workspace: Workspace, owner: User, board: Board}
 */
function rolesWorkspace(): array
{
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    return ['workspace' => $board->workspace, 'owner' => $owner, 'board' => $board];
}

test('a workspace exposes the four seeded system roles, all locked', function () {
    ['workspace' => $workspace] = rolesWorkspace();

    $system = $workspace->roles()->where('is_system', true)->pluck('key')->sort()->values()->all();

    expect($system)->toBe(['admin', 'member', 'observer', 'owner']);
});

test('an admin can create a custom role (board view is always granted)', function () {
    ['workspace' => $workspace, 'owner' => $owner] = rolesWorkspace();

    Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->call('startCreate')
        ->set('roleName', 'Relecteur')
        ->call('togglePermission', Permission::CommentPost->value)
        ->call('saveRole')
        ->assertHasNoErrors();

    $role = $workspace->roles()->where('key', 'relecteur')->firstOrFail();

    expect($role->is_system)->toBeFalse()
        ->and($role->permissions)->toContain(Permission::CommentPost->value)
        ->and($role->permissions)->toContain(Permission::BoardView->value);
});

test('editing a custom role updates its permissions', function () {
    ['workspace' => $workspace, 'owner' => $owner] = rolesWorkspace();
    $role = $workspace->roles()->create(['key' => 'reviewer', 'name' => 'Reviewer', 'permissions' => [Permission::BoardView->value], 'is_system' => false, 'position' => 5]);

    Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->call('startEdit', $role->id)
        ->set('roleName', 'Relecteur senior')
        ->call('togglePermission', Permission::CardManage->value)
        ->call('saveRole');

    $role->refresh();

    expect($role->name)->toBe('Relecteur senior')
        ->and($role->permissions)->toContain(Permission::CardManage->value);
});

test('an admin can edit a system role and the new permission is enforced', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'board' => $board] = rolesWorkspace();
    $observerRole = $workspace->roles()->where('key', 'observer')->firstOrFail();
    $user = User::factory()->create();
    $board->members()->attach($user, ['role' => 'observer']);

    expect($board->userCan($user->fresh(), Permission::CommentPost))->toBeFalse();

    Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->call('startEdit', $observerRole->id)
        ->call('togglePermission', Permission::CommentPost->value)
        ->call('saveRole')
        ->assertHasNoErrors();

    expect($observerRole->fresh()->permissions)->toContain(Permission::CommentPost->value)
        ->and($board->userCan($user->fresh(), Permission::CommentPost))->toBeTrue();
});

test('the owner role cannot be edited or deleted (recovery anchor)', function () {
    ['workspace' => $workspace, 'owner' => $owner] = rolesWorkspace();
    $ownerRole = $workspace->roles()->where('key', 'owner')->firstOrFail();

    // Owner is filtered out of the editable set → the lookup finds nothing.
    expect(fn () => Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->call('startEdit', $ownerRole->id))
        ->toThrow(ModelNotFoundException::class);

    expect($ownerRole->fresh()->permissions)->toBe(Permission::values());
});

test('deleting a custom role reassigns its members to Member', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'board' => $board] = rolesWorkspace();
    $role = $workspace->roles()->create(['key' => 'reviewer', 'name' => 'Reviewer', 'permissions' => [Permission::BoardView->value], 'is_system' => false, 'position' => 5]);
    $user = User::factory()->create();
    $board->members()->attach($user, ['role' => 'reviewer']);

    Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->call('deleteRole', $role->id);

    expect($workspace->roles()->whereKey($role->id)->exists())->toBeFalse()
        ->and($board->members()->whereKey($user->id)->first()->pivot->role)->toBe('member');
});

test('custom roles can be copied to another administered workspace', function () {
    ['workspace' => $workspace, 'owner' => $owner] = rolesWorkspace();
    $workspace->roles()->create(['key' => 'reviewer', 'name' => 'Reviewer', 'permissions' => [Permission::BoardView->value, Permission::CommentPost->value], 'is_system' => false, 'position' => 5]);

    $target = Workspace::factory()->create(['owner_id' => $owner->id]);
    $target->members()->attach($owner, ['role' => 'owner']);

    Livewire::actingAs($owner)->test(Roles::class, ['workspace' => $workspace])
        ->set('copyTargetId', $target->public_id)
        ->call('copyRolesTo');

    expect($target->roles()->where('key', 'reviewer')->exists())->toBeTrue();
});

test('a non-admin cannot open the roles screen', function () {
    ['workspace' => $workspace] = rolesWorkspace();

    Livewire::actingAs(User::factory()->create())->test(Roles::class, ['workspace' => $workspace])
        ->assertForbidden();
});

test('a custom board role can be assigned and is enforced', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'board' => $board] = rolesWorkspace();
    $workspace->roles()->create(['key' => 'reviewer', 'name' => 'Reviewer', 'permissions' => [Permission::BoardView->value, Permission::CommentPost->value], 'is_system' => false, 'position' => 5]);
    $user = User::factory()->create();
    $board->members()->attach($user, ['role' => 'member']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('updateBoardMemberRole', $user->id, 'reviewer');

    expect($board->members()->whereKey($user->id)->first()->pivot->role)->toBe('reviewer')
        ->and($board->userCan($user->fresh(), Permission::CommentPost))->toBeTrue()
        ->and($board->userCan($user->fresh(), Permission::CardManage))->toBeFalse();
});
