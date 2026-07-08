<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardLink;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('switching to the timeline view shows a dated card in its list lane', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Sprint']);
    Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'title' => 'Feature A',
        'start_at' => now()->addDay()->setTime(9, 0),
        'due_at' => now()->addDays(5)->setTime(18, 0),
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertSet('view', 'timeline')
        ->assertSee('Feature A')
        ->assertSee('Sprint');
});

test('a card without any date is absent from the timeline', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'title' => 'Sans date',
        'start_at' => null,
        'due_at' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertDontSee('Sans date');
});

test('timeline navigation shifts the window by weeks', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $monday = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertSet('timelineStart', $monday)
        ->call('timelineStep', 2)
        ->assertSet('timelineStart', now()->startOfWeek(Carbon::MONDAY)->addWeeks(2)->format('Y-m-d'))
        ->call('timelineToday')
        ->assertSet('timelineStart', $monday);
});

test('setCardSchedule sets both dates and keeps the time-of-day', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'start_at' => now()->addDay()->setTime(9, 30),
        'due_at' => now()->addDays(3)->setTime(17, 0),
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setCardSchedule', $card->id, '2026-09-01', '2026-09-04')
        ->assertHasNoErrors();

    $card->refresh();

    expect($card->start_at->format('Y-m-d H:i'))->toBe('2026-09-01 09:30')
        ->and($card->due_at->format('Y-m-d H:i'))->toBe('2026-09-04 17:00');
});

test('setCardSchedule ignores an inverted range (due before start)', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'start_at' => now()->setTime(12, 0),
        'due_at' => now()->addDay()->setTime(12, 0),
    ]);
    $originalDue = $card->due_at->toDateString();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setCardSchedule', $card->id, '2026-09-10', '2026-09-01');

    expect($card->fresh()->due_at->toDateString())->toBe($originalDue);
});

test('setCardSchedule can set only the due date (noon default) on an undated card', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create([
        'board_list_id' => $list->id,
        'board_id' => $board->id,
        'start_at' => null,
        'due_at' => null,
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setCardSchedule', $card->id, null, '2026-09-15');

    $card->refresh();

    expect($card->start_at)->toBeNull()
        ->and($card->due_at->format('Y-m-d H:i'))->toBe('2026-09-15 12:00');
});

test('the timeline draws a dependency arrow between two blocking cards in the window', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $a = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addDays(2)->setTime(12, 0)]);
    $b = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addDays(6)->setTime(12, 0)]);
    CardLink::create(['card_id' => $a->id, 'related_card_id' => $b->id, 'type' => 'blocks']);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertSee('tl-arrow');
});

test('the timeline shows no arrows when there are no dependencies', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addDays(2)->setTime(12, 0)]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertDontSee('tl-arrow');
});

test('a dependency to a card outside the window draws no arrow', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $a = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addDays(2)->setTime(12, 0)]);
    $far = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addDays(200)->setTime(12, 0)]);
    CardLink::create(['card_id' => $a->id, 'related_card_id' => $far->id, 'type' => 'blocks']);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('setView', 'timeline')
        ->assertDontSee('tl-arrow');
});
