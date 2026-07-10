<?php

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('the dashboard lists boards the user can access', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    $memberBoard = Board::factory()->create(['workspace_id' => $workspace->id, 'visibility' => BoardVisibility::Private]);
    $memberBoard->members()->attach($user, ['role' => Role::Owner->value]);

    $workspaceBoard = Board::factory()->create(['workspace_id' => $workspace->id, 'visibility' => BoardVisibility::Workspace]);

    $hiddenBoard = Board::factory()->create(['workspace_id' => $workspace->id, 'visibility' => BoardVisibility::Private]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee($memberBoard->name)
        ->assertSee($workspaceBoard->name)
        ->assertDontSee($hiddenBoard->name);
});

test('a user can create a workspace and becomes its owner', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('newWorkspaceName', 'Ma Team')
        ->call('createWorkspace')
        ->assertHasNoErrors();

    $workspace = Workspace::where('name', 'Ma Team')->first();

    expect($workspace)->not->toBeNull()
        ->and($workspace->owner_id)->toBe($user->id)
        ->and($workspace->memberRole($user))->toBe(Role::Owner);
});

test('a user can create a board with default lists', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set("newBoardName.{$workspace->id}", 'Nouveau Board')
        ->call('createBoard', $workspace->id)
        ->assertRedirect();

    $board = Board::where('name', 'Nouveau Board')->first();

    expect($board)->not->toBeNull()
        ->and($board->lists()->count())->toBe(3)
        ->and($board->memberRole($user))->toBe(Role::Owner);
});

test('an owner can rename their workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('startRenameWorkspace', $workspace->id)
        ->assertSet('renamingWorkspaceId', $workspace->id)
        ->set('workspaceNameDraft', 'Renommé')
        ->call('renameWorkspace')
        ->assertSet('renamingWorkspaceId', null);

    expect($workspace->fresh()->name)->toBe('Renommé');
});

test('an owner can delete their workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('deleteWorkspace', $workspace->id);

    expect(Workspace::whereKey($workspace->id)->exists())->toBeFalse();
});

test('a non-admin member cannot rename or delete a workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)
        ->test(Dashboard::class)
        ->call('startRenameWorkspace', $workspace->id)
        ->assertForbidden();

    Livewire::actingAs($member)
        ->test(Dashboard::class)
        ->call('deleteWorkspace', $workspace->id)
        ->assertForbidden();

    expect(Workspace::whereKey($workspace->id)->exists())->toBeTrue();
});

test('a user can pin and unpin a board, and it surfaces in the pinned section', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'visibility' => BoardVisibility::Workspace]);

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    $component->call('togglePin', $board->id)
        ->assertViewHas('pinnedBoards', fn ($pinned) => $pinned->contains('id', $board->id));
    expect($user->pinnedBoards()->whereKey($board->id)->exists())->toBeTrue();

    $component->call('togglePin', $board->id);
    expect($user->pinnedBoards()->whereKey($board->id)->exists())->toBeFalse();
});

test('a user cannot pin a board they cannot view', function () {
    $user = User::factory()->create();
    $board = Board::factory()->create(['visibility' => BoardVisibility::Private]); // not a member

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('togglePin', $board->id)
        ->assertForbidden();

    expect($user->pinnedBoards()->whereKey($board->id)->exists())->toBeFalse();
});

test('a board admin can rename and delete a board from the dashboard', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('startRenameBoard', $board->id)
        ->assertSet('renamingBoardId', $board->id)
        ->set('boardNameDraft', 'Renommé')
        ->call('renameBoard')
        ->assertSet('renamingBoardId', null);
    expect($board->fresh()->name)->toBe('Renommé');

    Livewire::actingAs($user)->test(Dashboard::class)->call('deleteBoard', $board->id);
    expect(Board::whereKey($board->id)->exists())->toBeFalse();
});

test('a non-admin board member cannot rename, delete or manage members from the dashboard', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)->test(Dashboard::class)->call('startRenameBoard', $board->id)->assertForbidden();
    Livewire::actingAs($member)->test(Dashboard::class)->call('deleteBoard', $board->id)->assertForbidden();
    Livewire::actingAs($member)->test(Dashboard::class)->call('openBoardMembers', $board->id)->assertForbidden();

    expect(Board::whereKey($board->id)->exists())->toBeTrue();
});

test('a board admin can add and remove a workspace member via the dashboard modal', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($other, ['role' => Role::Member->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    $component = Livewire::actingAs($owner)->test(Dashboard::class)
        ->call('openBoardMembers', $board->id)
        ->assertSet('managingMembersBoardId', $board->id);

    $component->call('addBoardMember', $other->id);
    expect($board->members()->whereKey($other->id)->exists())->toBeTrue();

    $component->call('removeBoardMember', $other->id);
    expect($board->members()->whereKey($other->id)->exists())->toBeFalse();

    // The owner is protected.
    $component->call('removeBoardMember', $owner->id);
    expect($board->members()->whereKey($owner->id)->exists())->toBeTrue();
});

test('only workspace members can be added to a board from the dashboard', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create(); // not a workspace member
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    Livewire::actingAs($owner)->test(Dashboard::class)
        ->call('openBoardMembers', $board->id)
        ->call('addBoardMember', $outsider->id);

    expect($board->members()->whereKey($outsider->id)->exists())->toBeFalse();
});

test('a user cannot create a board in a workspace they do not belong to', function () {
    $user = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create();

    expect(fn () => Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set("newBoardName.{$otherWorkspace->id}", 'Intrus')
        ->call('createBoard', $otherWorkspace->id))
        ->toThrow(ModelNotFoundException::class);

    expect(Board::where('name', 'Intrus')->exists())->toBeFalse();
});
