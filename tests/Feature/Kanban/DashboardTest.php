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
