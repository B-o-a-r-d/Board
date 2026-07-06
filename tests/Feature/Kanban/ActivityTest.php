<?php

use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Activity;
use App\Models\BoardList;
use Livewire\Livewire;

test('creating a card logs an activity', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", 'Tâche X')
        ->call('addCard', $list->id);

    $card = $board->cards()->where('title', 'Tâche X')->firstOrFail();

    expect(Activity::where('card_id', $card->id)->where('type', 'card.created')->exists())->toBeTrue();
});

test('moving a card across lists logs an activity', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Terminé']);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('moveCard', $card->id, 0, $target->id);

    expect(Activity::where('card_id', $card->id)->where('type', 'card.moved')->exists())->toBeTrue();
});

test('commenting and completing a card log activities', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Hello')
        ->call('addComment')
        ->call('toggleComplete');

    expect(Activity::where('card_id', $card->id)->where('type', 'comment.created')->exists())->toBeTrue()
        ->and(Activity::where('card_id', $card->id)->where('type', 'card.completed')->exists())->toBeTrue();
});
