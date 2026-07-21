<?php

use App\Enums\Role;
use App\Livewire\Dashboard;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use App\Plugins\WorkspaceTypes;
use Livewire\Livewire;

/**
 * Plugin-contributed workspace types (SDK ProvidesWorkspaceType). The Acme test
 * fixture contributes the 'acmespace' type backed by the acme-space.show route.
 */

/**
 * @return array{user: User, workspace: Workspace}
 */
function typedWorkspaceContext(string $type = 'acmespace'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'type' => $type]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    return compact('user', 'workspace');
}

test('active plugins contribute workspace types with a resolvable route', function () {
    $types = app(WorkspaceTypes::class)->all();

    expect($types)->toHaveKey('acmespace')
        ->and($types['acmespace']['label'])->toBe('Acme Space')
        ->and($types['acmespace']['route'])->toBe('acme-space.show');
});

test('a workspace can be created with a plugin type, unknown types fall back to kanban', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('newWorkspaceName', 'Docs équipe')
        ->set('newWorkspaceType', 'acmespace')
        ->call('createWorkspace');

    expect(Workspace::where('name', 'Docs équipe')->firstOrFail()->type)->toBe('acmespace');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('newWorkspaceName', 'Type inconnu')
        ->set('newWorkspaceType', 'martien')
        ->call('createWorkspace');

    expect(Workspace::where('name', 'Type inconnu')->firstOrFail()->type)->toBe(Workspace::TYPE_KANBAN);
});

test('the dashboard renders a typed workspace as an open tile, not a boards grid', function () {
    ['user' => $user, 'workspace' => $workspace] = typedWorkspaceContext();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Acme Space')
        ->assertSeeHtml(route('acme-space.show', $workspace))
        ->assertDontSee(__('+ Nouveau board'));
});

test('a typed workspace whose plugin is gone shows the Power-Up required notice', function () {
    ['user' => $user] = typedWorkspaceContext('vanished-type');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee(__('Power-Up requis'));
});

test('boards cannot be created in or moved into a typed workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = typedWorkspaceContext();

    // Creation is a silent no-op.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->set("newBoardName.{$workspace->id}", 'Interdit')
        ->call('createBoard', $workspace->id);
    expect(Board::where('name', 'Interdit')->exists())->toBeFalse();

    // Moving a board into it is refused too.
    $kanban = Workspace::factory()->create(['owner_id' => $user->id]);
    $kanban->members()->attach($user, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $kanban->id]);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('moveBoardToWorkspace', $board->id, $workspace->id);

    expect($board->fresh()->workspace_id)->toBe($kanban->id);
});
