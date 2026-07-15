<?php

use App\Livewire\Boards\Show;
use Livewire\Livewire;

/**
 * Show::onBoardActivity scopes the board's own re-render: on the kanban view a
 * card-level broadcast (listIds present) is left to the ListColumns and Show
 * skips rendering, while a board-level broadcast (no listIds — list added/renamed,
 * board renamed…) re-renders the chrome. skipRender keeps the previously rendered
 * HTML, so a mid-flight DB change is invisible until a board-level refresh.
 */
test('on the board view a card-level broadcast skips Show re-render, a board-level one refreshes', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = $board->lists()->firstOrFail();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->assertSet('view', 'board');

    // Rename the list straight in the DB, after the first render.
    $list->update(['name' => 'Zephyr Sprint 42']);

    // Card-level activity (scoped to a list) → ListColumns handle it, Show skips.
    $component->call('onBoardActivity', ['listIds' => [$list->id]])
        ->assertDontSee('Zephyr Sprint 42');

    // Board-level activity (no listIds) → Show re-renders its list headers.
    $component->call('onBoardActivity', [])
        ->assertSee('Zephyr Sprint 42');
});

test('outside the board view every broadcast refreshes Show (cards render there directly)', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = $board->lists()->firstOrFail();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);
    $component->set('view', 'table');

    $list->update(['name' => 'Nebula List 7']);

    // Even a card-level broadcast must refresh the table view (it renders cards).
    $component->call('onBoardActivity', ['listIds' => [$list->id]])
        ->assertSee('Nebula List 7');
});
