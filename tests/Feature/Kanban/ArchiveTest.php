<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use Livewire\Livewire;

test('archiving a list hides it with its cards, restoring brings them back', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Sprint']);
    $a = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Alpha']);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Beta']);

    $component = Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->assertSee('Sprint')
        ->assertSee('Carte Alpha');

    $component->call('archiveList', $list->id)
        ->assertDontSee('Sprint')
        ->assertDontSee('Carte Alpha');

    // The list is archived but its cards are untouched (just hidden with the list).
    expect($list->fresh()->archived_at)->not->toBeNull()
        ->and($a->fresh()->archived_at)->toBeNull();

    $component->call('restoreList', $list->id)
        ->assertSee('Sprint')
        ->assertSee('Carte Alpha')
        ->assertSee('Carte Beta');
});

test('archiving a card hides it, restoring brings it back', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte Zeta']);

    $component = Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->assertSee('Carte Zeta');

    $component->call('archiveCard', $card->id)->assertDontSee('Carte Zeta');
    expect($card->fresh()->archived_at)->not->toBeNull();

    $component->call('restoreCard', $card->id)->assertSee('Carte Zeta');
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
