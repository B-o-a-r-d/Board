<?php

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{workspace: Workspace, owner: User, admin: User, member: User, outsider: User}
 */
function makeWorkspaceWithRoles(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($admin, ['role' => Role::Admin->value]);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    return compact('workspace', 'owner', 'admin', 'member', 'outsider');
}

test('workspace members can view but only admins can manage', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'admin' => $admin, 'member' => $member, 'outsider' => $outsider] = makeWorkspaceWithRoles();

    expect($owner->can('view', $workspace))->toBeTrue()
        ->and($member->can('view', $workspace))->toBeTrue()
        ->and($outsider->can('view', $workspace))->toBeFalse()
        ->and($admin->can('update', $workspace))->toBeTrue()
        ->and($member->can('update', $workspace))->toBeFalse()
        ->and($admin->can('delete', $workspace))->toBeFalse()
        ->and($owner->can('delete', $workspace))->toBeTrue();
});

test('a private board is only visible to its members and workspace admins', function () {
    ['workspace' => $workspace, 'admin' => $admin, 'member' => $member, 'outsider' => $outsider] = makeWorkspaceWithRoles();

    $boardMember = User::factory()->create();
    $workspace->members()->attach($boardMember, ['role' => Role::Member->value]);

    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'visibility' => BoardVisibility::Private,
    ]);
    $board->members()->attach($boardMember, ['role' => Role::Member->value]);

    expect($boardMember->can('view', $board))->toBeTrue()
        ->and($admin->can('view', $board))->toBeTrue()      // workspace admin
        ->and($member->can('view', $board))->toBeFalse()    // workspace member, not board member
        ->and($outsider->can('view', $board))->toBeFalse();
});

test('a workspace-visible board is visible to every workspace member', function () {
    ['workspace' => $workspace, 'member' => $member, 'outsider' => $outsider] = makeWorkspaceWithRoles();

    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'visibility' => BoardVisibility::Workspace,
    ]);

    expect($member->can('view', $board))->toBeTrue()
        ->and($outsider->can('view', $board))->toBeFalse();
});

test('board administration is limited to board and workspace admins', function () {
    ['workspace' => $workspace, 'admin' => $admin, 'member' => $member] = makeWorkspaceWithRoles();

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);

    expect($admin->can('update', $board))->toBeTrue()       // workspace admin
        ->and($member->can('update', $board))->toBeFalse()
        ->and($member->can('delete', $board))->toBeFalse();
});

test('cards inherit board view, but editing needs a contributing role', function () {
    ['workspace' => $workspace, 'member' => $member, 'outsider' => $outsider] = makeWorkspaceWithRoles();

    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'visibility' => BoardVisibility::Workspace,
    ]);
    $card = Card::factory()->create(['board_id' => $board->id]);

    // A workspace member sees a workspace-visible board's cards, but may only
    // edit them once given a contributing board role (RBAC).
    expect($member->can('view', $card))->toBeTrue()
        ->and($member->can('update', $card))->toBeFalse()
        ->and($outsider->can('view', $card))->toBeFalse();

    // Add them to the board as a Member → now they can contribute.
    $board->members()->attach($member, ['role' => 'member']);

    expect($member->can('update', $card->fresh()))->toBeTrue();
});
