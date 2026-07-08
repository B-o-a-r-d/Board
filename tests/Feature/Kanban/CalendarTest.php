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

test('rescheduleCard moves a due card to the dropped day and keeps the time', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'due_at' => now()->startOfMonth()->addDays(4)->setTime(15, 30),
    ]);

    $target = now()->startOfMonth()->addDays(18)->toDateString();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('rescheduleCard', $card->id, $target)
        ->assertHasNoErrors();

    $card->refresh();

    expect($card->due_at->toDateString())->toBe($target)
        ->and($card->due_at->format('H:i'))->toBe('15:30');
});

test('rescheduleCard shifts the start date when the card has no due date', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'due_at' => null,
        'start_at' => now()->startOfMonth()->addDays(2)->setTime(9, 0),
    ]);

    $target = now()->startOfMonth()->addDays(20)->toDateString();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('rescheduleCard', $card->id, $target);

    $card->refresh();

    expect($card->due_at)->toBeNull()
        ->and($card->start_at->toDateString())->toBe($target);
});

test('rescheduleCard sets a due date on a card that had none', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'due_at' => null,
        'start_at' => null,
    ]);

    $target = now()->startOfMonth()->addDays(7)->toDateString();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('rescheduleCard', $card->id, $target);

    expect($card->refresh()->due_at->toDateString())->toBe($target);
});

test('createCardOnDate creates a dated card in the board first list', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $firstList = $board->lists()->orderBy('position')->firstOrFail();

    $target = now()->startOfMonth()->addDays(11)->toDateString();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('createCardOnDate', $target, 'Réunion équipe')
        ->assertHasNoErrors();

    $card = Card::where('title', 'Réunion équipe')->firstOrFail();

    expect($card->board_list_id)->toBe($firstList->id)
        ->and($card->due_at->toDateString())->toBe($target);
});

test('createCardOnDate ignores a blank title', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $before = Card::count();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('createCardOnDate', now()->toDateString(), '   ');

    expect(Card::count())->toBe($before);
});
