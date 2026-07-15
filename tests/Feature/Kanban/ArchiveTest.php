<?php

use App\Livewire\Boards\ListColumn;
use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use Livewire\Livewire;

test('archiving a list hides it with its cards, restoring brings them back', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Sprint']);
    $a = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Alpha']);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Beta']);

    // The list (and its column) is visible; its cards render inside the column.
    $component = Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->assertSee('Sprint');
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Carte Alpha')
        ->assertSee('Carte Beta');

    // Archiving the list removes it (and its column) from the board.
    $component->call('archiveList', $list->id)->assertDontSee('Sprint');

    // The list is archived but its cards are untouched (just hidden with the list).
    expect($list->fresh()->archived_at)->not->toBeNull()
        ->and($a->fresh()->archived_at)->toBeNull();

    // Restoring brings the list back, cards intact.
    $component->call('restoreList', $list->id)->assertSee('Sprint');
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list->fresh()])
        ->assertSee('Carte Alpha')
        ->assertSee('Carte Beta');
});

test('archiving a card hides it, restoring brings it back', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Zeta']);

    // The card lives in its list column; archiving it there hides it.
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Carte Zeta')
        ->call('archiveCard', $card->id)
        ->assertDontSee('Carte Zeta');
    expect($card->fresh()->archived_at)->not->toBeNull();

    // Restoring (trash panel action on Show) brings it back to the column.
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])->call('restoreCard', $card->id);
    expect($card->fresh()->archived_at)->toBeNull();
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Carte Zeta');
});

test('the trash panel lists archived items and can delete them permanently', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Vieille liste', 'archived_at' => now()]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte morte', 'archived_at' => now()]);

    $component = Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('toggleTrash')
        ->assertSee('Vieille liste')
        ->assertSee('Carte morte');

    $component->call('deleteCardPermanently', $card->id);
    expect(Card::whereKey($card->id)->exists())->toBeFalse();

    $component->call('deleteListPermanently', $list->id);
    expect(BoardList::whereKey($list->id)->exists())->toBeFalse();
});
