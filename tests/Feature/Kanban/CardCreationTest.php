<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use Livewire\Livewire;

test('creating a card via the quick-add field opens its detail modal', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", 'Fresh card')
        ->call('addCard', $list->id)
        ->assertHasNoErrors()
        ->assertDispatched('open-card');

    expect($board->cards()->where('title', 'Fresh card')->exists())->toBeTrue();
});

test('an empty quick-add title creates nothing and opens no modal', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", '   ')
        ->call('addCard', $list->id)
        ->assertNotDispatched('open-card');
});
