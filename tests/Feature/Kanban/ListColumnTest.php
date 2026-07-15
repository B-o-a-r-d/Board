<?php

use App\Enums\Role;
use App\Livewire\Boards\ListColumn;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Livewire\Livewire;

/**
 * The per-list ListColumn owns card rendering + card mutations, so a card action
 * re-renders only its column instead of the whole board.
 */
test('addCard creates a card in the column and opens it', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->set("newCardTitle.{$card->board_list_id}", 'Nouvelle tâche')
        ->call('addCard', $card->board_list_id)
        ->assertSet("newCardTitle.{$card->board_list_id}", '')
        ->assertDispatched('open-card')
        ->assertSee('Nouvelle tâche');

    expect($card->list->cards()->where('title', 'Nouvelle tâche')->exists())->toBeTrue();
});

test('toggleCardComplete flips the completion state', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('toggleCardComplete', $card->id);
    expect($card->fresh()->completed_at)->not->toBeNull();

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('toggleCardComplete', $card->id);
    expect($card->fresh()->completed_at)->toBeNull();
});

test('archiveCard removes the card from its column', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'À archiver']);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('archiveCard', $card->id)
        ->assertDontSee('À archiver');

    expect($card->fresh()->archived_at)->not->toBeNull();
});

test('duplicateCard copies the card into the same list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Original']);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('duplicateCard', $card->id);

    expect($card->list->cards()->where('title', 'Original (copie)')->exists())->toBeTrue();
});

test('moveCardToList moves the card and asks the target column to refresh', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('moveCardToList', $card->id, $target->id)
        ->assertDispatched('cards:refresh');

    expect($card->fresh()->board_list_id)->toBe($target->id);
});

test('a heavy list paints one page then loadMore reveals the rest', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    // 55 cards, page size 50 → 5 remain after the first page.
    for ($i = 0; $i < 55; $i++) {
        Card::factory()->create([
            'board_id' => $board->id,
            'board_list_id' => $list->id,
            'title' => "Carte no {$i}",
            'position' => $i,
        ]);
    }

    $component = Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Carte no 0')
        ->assertSee('Carte no 49')
        ->assertDontSee('Carte no 54')
        ->assertSee(__('Charger plus'));

    $component->call('loadMore')
        ->assertSee('Carte no 54')
        ->assertDontSee(__('Charger plus'));
});

test('a read-only viewer cannot mutate cards in a column', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $viewer = User::factory()->create();
    $board->members()->attach($viewer, ['role' => Role::Observer->value]);

    Livewire::actingAs($viewer)->test(ListColumn::class, ['board' => $board, 'list' => $card->list])
        ->call('archiveCard', $card->id)
        ->assertForbidden();

    expect($card->fresh()->archived_at)->toBeNull();
});
