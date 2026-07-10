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
