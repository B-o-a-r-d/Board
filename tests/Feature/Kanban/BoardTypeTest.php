<?php

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Livewire\Dashboard;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use App\Plugins\BoardTypes;
use Livewire\Livewire;

/**
 * Plugin-contributed board types (SDK ProvidesBoardType). The Acme test fixture
 * contributes the 'acmeboard' type backed by the acme-board.show route.
 */

/**
 * @return array{user: User, workspace: Workspace}
 */
function boardTypeContext(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    return compact('user', 'workspace');
}

test('active plugins contribute board types with a resolvable route', function () {
    $types = app(BoardTypes::class)->all();

    expect($types)->toHaveKey('acmeboard')
        ->and($types['acmeboard']['label'])->toBe('Acme Board')
        ->and($types['acmeboard']['route'])->toBe('acme-board.show');
});

test('a board can be created with a plugin type — no default lists, routed to the plugin page', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set("newBoardName.{$workspace->id}", 'Mon étagère')
        ->set("newBoardType.{$workspace->id}", 'acmeboard')
        ->call('createBoard', $workspace->id)
        ->assertRedirect(route('acme-board.show', Board::where('name', 'Mon étagère')->firstOrFail()));

    $board = Board::where('name', 'Mon étagère')->firstOrFail();

    expect($board->type)->toBe('acmeboard')
        ->and($board->lists()->count())->toBe(0)
        ->and($board->members()->whereKey($user->id)->exists())->toBeTrue();
});

test('an unknown board type falls back to kanban with its default lists', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set("newBoardName.{$workspace->id}", 'Type martien')
        ->set("newBoardType.{$workspace->id}", 'martien')
        ->call('createBoard', $workspace->id);

    $board = Board::where('name', 'Type martien')->firstOrFail();

    expect($board->type)->toBe(Board::TYPE_KANBAN)
        ->and($board->lists()->count())->toBe(3);
});

test('the dashboard routes a typed board card to its plugin page and shows the type', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'type' => 'acmeboard']);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSeeHtml(route('acme-board.show', $board))
        ->assertSee('Acme Board');
});

test('a typed board whose plugin is gone shows Power-Up requis and loses its link', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'type' => 'vanished']);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee(__('Power-Up requis'))
        ->assertDontSeeHtml(route('boards.show', $board));
});

test('a typed board never opens the kanban UI', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();
    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'type' => 'acmeboard']);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    Livewire::actingAs($user)->test(Show::class, ['board' => $board])
        ->assertStatus(404);
});

test('the topbar switcher only lists kanban boards', function () {
    ['user' => $user, 'workspace' => $workspace] = boardTypeContext();
    $kanban = Board::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Tableau kanban']);
    $kanban->members()->attach($user, ['role' => Role::Owner->value]);
    Board::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Étagère typée', 'type' => 'acmeboard', 'visibility' => BoardVisibility::Workspace]);

    Livewire::actingAs($user)->test(Show::class, ['board' => $kanban])
        ->assertSee('Tableau kanban')
        ->assertDontSee('Étagère typée');
});
