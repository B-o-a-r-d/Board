<?php

use App\Enums\Role;
use App\Livewire\Boards\Automations;
use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use Livewire\Livewire;

/** Phase 7: immediate list actions + automation shortcuts from the list menu. */
test('sortListNow orders the list cards by the chosen criterion', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['due_at' => now()->addDays(5), 'position' => 0]);
    $soon = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'due_at' => now()->addDay(), 'position' => 1]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('sortListNow', $card->board_list_id, 'due');

    expect($soon->fresh()->position)->toBe(0)
        ->and($card->fresh()->position)->toBe(1);
});

test('moveListCardsNow moves every live card to the target list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $other = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id]);
    $target = BoardList::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('moveListCardsNow', $card->board_list_id, $target->id);

    expect($card->fresh()->board_list_id)->toBe($target->id)
        ->and($other->fresh()->board_list_id)->toBe($target->id);
});

test('archiveListCardsNow archives every live card of the list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $other = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('archiveListCardsNow', $card->board_list_id);

    expect($card->fresh()->archived_at)->not->toBeNull()
        ->and($other->fresh()->archived_at)->not->toBeNull();
});

test('immediate list actions require contribution', function () {
    ['board' => $board, 'outsider' => $observer, 'card' => $card] = makeCardContext();
    $board->workspace->members()->attach($observer, ['role' => Role::Observer->value]);
    $board->members()->attach($observer, ['role' => Role::Observer->value]);

    Livewire::actingAs($observer)->test(Show::class, ['board' => $board])
        ->call('archiveListCardsNow', $card->board_list_id)
        ->assertForbidden();

    expect($card->fresh()->archived_at)->toBeNull();
});

test('a list menu shortcut prefills the builder wizard', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('open', [
            'section' => 'scheduled',
            'triggerConfig' => ['freq' => 'daily', 'at' => '09:00'],
            'actions' => [['type' => 'sort_list', 'config' => ['list_id' => $card->board_list_id, 'by' => 'due']]],
            'step' => 2,
        ])
        ->assertSet('building', true)
        ->assertSet('section', 'scheduled')
        ->assertSet('step', 2)
        ->assertSet('triggerType', 'scheduled')
        ->assertSet('actions.0.type', 'sort_list');
});

test('re-sorting on the same criterion inverts the direction and shows it on the list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Alpha', 'position' => 0]);
    $zeta = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'title' => 'Zeta', 'position' => 1]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    // First click: ascending (Alpha before Zeta), refreshes the actor's column.
    $component->call('sortListNow', $card->board_list_id, 'title')
        ->assertDispatched('cards:refresh', listId: $card->board_list_id);
    $list = BoardList::findOrFail($card->board_list_id);
    expect($card->fresh()->position)->toBe(0)
        ->and($list->last_sorted_by)->toBe('title')
        ->and($list->last_sorted_dir)->toBe('asc');

    // Second click on the same criterion: inverted (Zeta first).
    $component->call('sortListNow', $card->board_list_id, 'title');
    expect($zeta->fresh()->position)->toBe(0)
        ->and($list->fresh()->last_sorted_dir)->toBe('desc');

    // Third click: back to ascending.
    $component->call('sortListNow', $card->board_list_id, 'title');
    expect($card->fresh()->position)->toBe(0)
        ->and($list->fresh()->last_sorted_dir)->toBe('asc');
});

test('sorting by due date descending still sinks undated cards to the bottom', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['due_at' => now()->addDay(), 'position' => 0]);
    $late = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'due_at' => now()->addDays(9), 'position' => 1]);
    $undated = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'due_at' => null, 'position' => 2]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);
    $component->call('sortListNow', $card->board_list_id, 'due');   // asc
    $component->call('sortListNow', $card->board_list_id, 'due');   // desc

    expect($late->fresh()->position)->toBe(0)
        ->and($card->fresh()->position)->toBe(1)
        ->and($undated->fresh()->position)->toBe(2);
});
