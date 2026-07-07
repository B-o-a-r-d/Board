<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use Livewire\Livewire;

test('switching to the calendar view shows a card on its due date', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'title' => 'Sortie v2',
        'due_at' => now()->startOfMonth()->addDays(9)->setTime(12, 0),
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'calendar')
        ->assertSet('view', 'calendar')
        ->assertSee('Sortie v2');
});

test('a card falls back to its start date when it has no due date', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'title' => 'Kickoff',
        'due_at' => null,
        'start_at' => now()->startOfMonth()->addDays(3)->setTime(9, 0),
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'calendar')
        ->assertSee('Kickoff');
});

test('a card without any date is not shown in the calendar', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'title' => 'Carte sans date',
        'due_at' => null,
        'start_at' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'calendar')
        ->assertDontSee('Carte sans date');
});

test('calendar navigation changes the displayed month', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $thisMonth = now()->format('Y-m');

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'calendar')
        ->assertSet('calendarMonth', $thisMonth)
        ->call('calendarStep', 1)
        ->assertSet('calendarMonth', now()->addMonth()->format('Y-m'))
        ->call('calendarStep', -2)
        ->assertSet('calendarMonth', now()->subMonth()->format('Y-m'))
        ->call('calendarToday')
        ->assertSet('calendarMonth', $thisMonth);
});

test('an unknown view value falls back to the board (no calendar crash)', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'bogus')
        ->assertSet('view', 'board')
        ->assertOk();
});
