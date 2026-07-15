<?php

use App\Enums\Role;
use App\Livewire\Boards\ListColumn;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\CardMirror;
use App\Models\User;
use Livewire\Livewire;

test('a card can be mirrored into another list on the same board', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Target']);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('mirrorListId', (string) $target->id)
        ->call('mirrorCard')
        ->assertHasNoErrors();

    expect(CardMirror::where('card_id', $card->id)->where('board_list_id', $target->id)->exists())->toBeTrue();
});

test('a mirrored card renders on its target list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);
    $mirror = CardMirror::create(['card_id' => $card->id, 'board_list_id' => $target->id, 'board_id' => $board->id, 'created_by' => $owner->id, 'position' => 0]);

    // Mirrors are rendered by the target list's column component.
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $target])
        ->assertSeeHtml('mirror-'.$mirror->id)
        ->assertSee($card->title);
});

test('removing a mirror deletes the placement but keeps the card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);
    $mirror = CardMirror::create(['card_id' => $card->id, 'board_list_id' => $target->id, 'board_id' => $board->id, 'created_by' => $owner->id, 'position' => 0]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('removeMirror', $mirror->id);

    expect(CardMirror::whereKey($mirror->id)->exists())->toBeFalse()
        ->and($card->fresh())->not->toBeNull();
});

test('a card can be mirrored onto another board in the workspace', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $boardB = Board::factory()->create(['workspace_id' => $board->workspace_id]);
    $boardB->members()->attach($owner, ['role' => 'owner']);
    $listB = BoardList::factory()->create(['board_id' => $boardB->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('mirrorListId', (string) $listB->id)
        ->call('mirrorCard')
        ->assertHasNoErrors();

    expect(CardMirror::where('card_id', $card->id)->where('board_id', $boardB->id)->exists())->toBeTrue();
});

test('a user cannot mirror onto a board they cannot contribute to', function () {
    ['board' => $board, 'member' => $member, 'card' => $card] = makeCardContext();
    $boardB = Board::factory()->create(['workspace_id' => $board->workspace_id, 'visibility' => 'private']);
    $listB = BoardList::factory()->create(['board_id' => $boardB->id]);

    Livewire::actingAs($member)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('mirrorListId', (string) $listB->id)
        ->call('mirrorCard')
        ->assertForbidden();

    expect(CardMirror::where('card_id', $card->id)->count())->toBe(0);
});

test('mirroring the same card into the same list twice is idempotent', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])->call('openCard', $card->id);
    $component->set('mirrorListId', (string) $target->id)->call('mirrorCard');
    $component->set('mirrorListId', (string) $target->id)->call('mirrorCard');

    expect(CardMirror::where('card_id', $card->id)->where('board_list_id', $target->id)->count())->toBe(1);
});

test('a read-only viewer of the source board cannot mirror its card elsewhere', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();

    // Observer on the source board A (view only), contributor on target board B.
    $viewer = User::factory()->create();
    $board->members()->attach($viewer, ['role' => Role::Observer->value]);

    $boardB = Board::factory()->create(['workspace_id' => $board->workspace_id]);
    $boardB->members()->attach($viewer, ['role' => Role::Owner->value]);
    $listB = BoardList::factory()->create(['board_id' => $boardB->id]);

    Livewire::actingAs($viewer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('mirrorListId', (string) $listB->id)
        ->call('mirrorCard')
        ->assertForbidden();

    expect(CardMirror::where('card_id', $card->id)->count())->toBe(0);
});

test('a read-only viewer of the source board cannot remove one of its mirrors', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);
    $mirror = CardMirror::create(['card_id' => $card->id, 'board_list_id' => $target->id, 'board_id' => $board->id, 'created_by' => $owner->id, 'position' => 0]);

    $viewer = User::factory()->create();
    $board->members()->attach($viewer, ['role' => Role::Observer->value]);

    Livewire::actingAs($viewer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('removeMirror', $mirror->id)
        ->assertForbidden();

    expect(CardMirror::whereKey($mirror->id)->exists())->toBeTrue();
});

test('a card cannot be mirrored into its own list', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('mirrorListId', (string) $card->board_list_id)
        ->call('mirrorCard')
        ->assertHasErrors('mirrorListId');

    expect(CardMirror::where('card_id', $card->id)->count())->toBe(0);
});
