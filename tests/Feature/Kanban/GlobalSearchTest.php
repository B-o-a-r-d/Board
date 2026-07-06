<?php

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Livewire\GlobalSearch;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

test('search finds accessible boards and cards', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $board->update(['name' => 'Plan Marketing']);
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Lancer la campagne']);

    Livewire::actingAs($owner)
        ->test(GlobalSearch::class)
        ->set('query', 'market')
        ->assertSee('Plan Marketing');

    Livewire::actingAs($owner)
        ->test(GlobalSearch::class)
        ->set('query', 'campagne')
        ->assertSee('Lancer la campagne');
});

test('search does not return boards the user cannot access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $board = Board::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Board Secret',
        'visibility' => BoardVisibility::Private,
    ]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    Livewire::actingAs($stranger)
        ->test(GlobalSearch::class)
        ->set('query', 'secret')
        ->assertDontSee('Board Secret');
});

test('queries shorter than two characters return nothing', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $board->update(['name' => 'Zephyr']);

    Livewire::actingAs($owner)
        ->test(GlobalSearch::class)
        ->set('query', 'z')
        ->assertDontSee('Zephyr');
});
