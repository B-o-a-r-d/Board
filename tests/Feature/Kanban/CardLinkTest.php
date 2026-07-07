<?php

use App\Livewire\Cards\CardDetail;
use App\Models\Card;
use App\Models\CardLink;
use Livewire\Livewire;

test('cards can be linked with a blocks relation and unlinked', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $a] = makeCardContext();
    $b = Card::factory()->create(['board_list_id' => $a->board_list_id, 'board_id' => $board->id, 'title' => 'Carte B']);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $a->id)
        ->set('linkType', 'blocks')
        ->call('linkCard', $b->id);

    expect(CardLink::where('card_id', $a->id)->where('related_card_id', $b->id)->where('type', 'blocks')->exists())->toBeTrue();

    $component->call('unlinkCard', CardLink::first()->id);
    expect(CardLink::count())->toBe(0);
});

test('blocked_by is normalised to a blocks row on the other card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $a] = makeCardContext();
    $b = Card::factory()->create(['board_list_id' => $a->board_list_id, 'board_id' => $board->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $a->id)
        ->call('linkCard', $b->id, 'blocked_by');

    // "A blocked_by B" is stored as "B blocks A".
    expect(CardLink::where('card_id', $b->id)->where('related_card_id', $a->id)->where('type', 'blocks')->exists())->toBeTrue();
});

test('relates_to is symmetric and stored once with the smaller id first', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $a] = makeCardContext();
    $b = Card::factory()->create(['board_list_id' => $a->board_list_id, 'board_id' => $board->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $a->id)
        ->call('linkCard', $b->id, 'relates_to')
        ->call('linkCard', $b->id, 'relates_to');

    expect(CardLink::where('type', 'relates_to')->count())->toBe(1);
    $link = CardLink::first();
    expect($link->card_id)->toBe(min($a->id, $b->id))
        ->and($link->related_card_id)->toBe(max($a->id, $b->id));
});

test('a card cannot be linked to itself or to a card on another board', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $a] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $a->id)
        ->call('linkCard', $a->id, 'blocks');
    expect(CardLink::count())->toBe(0);

    ['card' => $foreign] = makeCardContext();
    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $a->id)
        ->call('linkCard', $foreign->id, 'blocks');
    expect(CardLink::count())->toBe(0);
});
