<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, outsider: User}
 */
function makeBoard(): array
{
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return compact('board', 'owner', 'outsider');
}

test('a member can open the board and non-members cannot', function () {
    ['board' => $board, 'owner' => $owner, 'outsider' => $outsider] = makeBoard();

    $this->actingAs($owner)->get(route('boards.show', $board))->assertOk();
    $this->actingAs($outsider)->get(route('boards.show', $board))->assertForbidden();
});

test('a member can add, rename and delete a list', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->set('newListName', 'À faire')->call('addList')->assertHasNoErrors();

    $list = $board->lists()->firstOrFail();
    expect($list->name)->toBe('À faire');

    $component->call('renameList', $list->id, 'En cours');
    expect($list->fresh()->name)->toBe('En cours');

    $component->call('archiveList', $list->id);
    expect($list->fresh()->archived_at)->not->toBeNull()
        ->and($board->lists()->whereNull('archived_at')->count())->toBe(0);
});

test('a member can add and archive cards', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->set("newCardTitle.{$list->id}", 'Première carte')->call('addCard', $list->id);

    $card = $list->cards()->firstOrFail();
    expect($card->title)->toBe('Première carte')
        ->and($card->created_by)->toBe($owner->id)
        ->and($card->board_id)->toBe($board->id);

    $component->call('archiveCard', $card->id);
    expect($card->fresh()->archived_at)->not->toBeNull()
        ->and($list->cards()->whereNull('archived_at')->count())->toBe(0);
});

test('reordering cards within a list updates positions', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);

    $cards = Card::factory()->count(3)->sequence(
        ['position' => 0],
        ['position' => 1],
        ['position' => 2],
    )->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    // Move the first card to the last position (index 2) within the same list.
    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('moveCard', $cards[0]->id, 2, $list->id);

    $ordered = $list->cards()->orderBy('position')->pluck('id')->all();

    expect($ordered)->toBe([$cards[1]->id, $cards[2]->id, $cards[0]->id]);
});

test('moving a card to another list reassigns and resequences it', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();
    $source = BoardList::factory()->create(['board_id' => $board->id]);
    $target = BoardList::factory()->create(['board_id' => $board->id]);

    $card = Card::factory()->create(['board_list_id' => $source->id, 'board_id' => $board->id, 'position' => 0]);
    Card::factory()->create(['board_list_id' => $target->id, 'board_id' => $board->id, 'position' => 0]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('moveCard', $card->id, 0, $target->id);

    expect($card->fresh()->board_list_id)->toBe($target->id)
        ->and($target->cards()->count())->toBe(2)
        ->and($source->cards()->count())->toBe(0);
});

test('reordering lists updates their positions', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();

    $lists = BoardList::factory()->count(3)->sequence(
        ['position' => 0],
        ['position' => 1],
        ['position' => 2],
    )->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('reorderLists', $lists[2]->id, 0);

    $ordered = $board->lists()->orderBy('position')->pluck('id')->all();

    expect($ordered)->toBe([$lists[2]->id, $lists[0]->id, $lists[1]->id]);
});

test('a board admin can delete the board and its content', function () {
    ['board' => $board, 'owner' => $owner] = makeBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('deleteBoard')
        ->assertRedirect(route('dashboard'));

    expect(Board::whereKey($board->id)->exists())->toBeFalse()
        ->and(BoardList::whereKey($list->id)->exists())->toBeFalse()
        ->and(Card::whereKey($card->id)->exists())->toBeFalse();
});

test('a plain board member cannot delete the board', function () {
    ['board' => $board] = makeBoard();
    $member = User::factory()->create();
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)
        ->test(Show::class, ['board' => $board])
        ->call('deleteBoard')
        ->assertForbidden();

    expect(Board::whereKey($board->id)->exists())->toBeTrue();
});

test('outsiders are forbidden from the board component', function () {
    ['board' => $board, 'outsider' => $outsider] = makeBoard();

    Livewire::actingAs($outsider)
        ->test(Show::class, ['board' => $board])
        ->assertForbidden();
});
