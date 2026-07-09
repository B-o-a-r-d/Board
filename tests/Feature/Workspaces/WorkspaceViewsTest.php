<?php

use App\Livewire\Workspaces\Views;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{owner: User, workspace: Workspace, boardA: Board, boardB: Board, listA: BoardList, listB: BoardList}
 */
function wsViewsContext(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => 'owner']);

    $boardA = Board::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha', 'visibility' => 'private']);
    $boardB = Board::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Beta', 'visibility' => 'private']);
    $boardA->members()->attach($owner, ['role' => 'owner']);
    $boardB->members()->attach($owner, ['role' => 'owner']);

    $listA = BoardList::factory()->create(['board_id' => $boardA->id]);
    $listB = BoardList::factory()->create(['board_id' => $boardB->id]);

    return compact('owner', 'workspace', 'boardA', 'boardB', 'listA', 'listB');
}

test('a non-member cannot open the workspace views', function () {
    ['workspace' => $workspace] = wsViewsContext();

    Livewire::actingAs(User::factory()->create())->test(Views::class, ['workspace' => $workspace])
        ->assertForbidden();
});

test('a deactivated member cannot open the workspace views', function () {
    ['workspace' => $workspace] = wsViewsContext();
    $member = User::factory()->create();
    $workspace->members()->attach($member, ['role' => 'member', 'deactivated_at' => now()]);

    Livewire::actingAs($member)->test(Views::class, ['workspace' => $workspace])
        ->assertForbidden();
});

test('the table aggregates cards from every accessible board', function () {
    ['owner' => $owner, 'workspace' => $ws, 'boardA' => $a, 'boardB' => $b, 'listA' => $la, 'listB' => $lb] = wsViewsContext();
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Aaa card']);
    Card::factory()->create(['board_id' => $b->id, 'board_list_id' => $lb->id, 'title' => 'Bbb card']);

    Livewire::actingAs($owner)->test(Views::class, ['workspace' => $ws, 'view' => 'table'])
        ->assertSee('Aaa card')
        ->assertSee('Bbb card')
        ->assertSee('Alpha')
        ->assertSee('Beta');
});

test('archived cards are excluded from the aggregation', function () {
    ['owner' => $owner, 'workspace' => $ws, 'boardA' => $a, 'listA' => $la] = wsViewsContext();
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Live card']);
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Gone card', 'archived_at' => now()]);

    Livewire::actingAs($owner)->test(Views::class, ['workspace' => $ws, 'view' => 'table'])
        ->assertSee('Live card')
        ->assertDontSee('Gone card');
});

test('a member only sees cards from boards they can view', function () {
    ['workspace' => $ws, 'boardA' => $a, 'boardB' => $b, 'listA' => $la, 'listB' => $lb] = wsViewsContext();
    $member = User::factory()->create();
    $ws->members()->attach($member, ['role' => 'member']);
    $a->members()->attach($member, ['role' => 'member']); // board A only

    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'On Alpha']);
    Card::factory()->create(['board_id' => $b->id, 'board_list_id' => $lb->id, 'title' => 'On Beta']);

    Livewire::actingAs($member)->test(Views::class, ['workspace' => $ws, 'view' => 'table'])
        ->assertSee('On Alpha')
        ->assertDontSee('On Beta');
});

test('the board filter narrows the aggregation', function () {
    ['owner' => $owner, 'workspace' => $ws, 'boardA' => $a, 'boardB' => $b, 'listA' => $la, 'listB' => $lb] = wsViewsContext();
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Only Alpha card']);
    Card::factory()->create(['board_id' => $b->id, 'board_list_id' => $lb->id, 'title' => 'Only Beta card']);

    Livewire::actingAs($owner)->test(Views::class, ['workspace' => $ws, 'view' => 'table'])
        ->set('filterBoards', [$a->id])
        ->assertSee('Only Alpha card')
        ->assertDontSee('Only Beta card');
});

test('the overdue filter shows only past-due incomplete cards', function () {
    ['owner' => $owner, 'workspace' => $ws, 'boardA' => $a, 'listA' => $la] = wsViewsContext();
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Late card', 'due_at' => now()->subDays(2)]);
    Card::factory()->create(['board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Future card', 'due_at' => now()->addDays(5)]);

    Livewire::actingAs($owner)->test(Views::class, ['workspace' => $ws, 'view' => 'table'])
        ->set('filterDue', 'overdue')
        ->assertSee('Late card')
        ->assertDontSee('Future card');
});

test('the calendar aggregates a dated card', function () {
    ['owner' => $owner, 'workspace' => $ws, 'boardA' => $a, 'listA' => $la] = wsViewsContext();
    Card::factory()->create([
        'board_id' => $a->id, 'board_list_id' => $la->id, 'title' => 'Dated card',
        'due_at' => now()->startOfMonth()->addDays(10)->setTime(12, 0),
    ]);

    Livewire::actingAs($owner)->test(Views::class, ['workspace' => $ws, 'view' => 'calendar'])
        ->assertSee('Dated card');
});
