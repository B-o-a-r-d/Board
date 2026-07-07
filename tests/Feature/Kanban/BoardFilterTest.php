<?php

use App\Livewire\Boards\Show;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, list: BoardList, a: Card, b: Card}
 */
function boardWithTwoCards(): array
{
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $a = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Corriger le login']);
    $b = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Refonte page accueil']);

    return compact('board', 'owner', 'list', 'a', 'b');
}

test('text search filters cards (case-insensitive)', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->assertSee('Corriger le login')
        ->assertSee('Refonte page accueil')
        ->set('search', 'LOGIN')
        ->assertSee('Corriger le login')
        ->assertDontSee('Refonte page accueil');
});

test('label filter shows only cards with that label', function () {
    ['board' => $board, 'owner' => $owner, 'a' => $a, 'b' => $b] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $a->labels()->attach($label);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->set('filterLabel', $label->id)
        ->assertSee($a->title)
        ->assertDontSee($b->title);
});

test('member filter shows only cards assigned to that member', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $mine = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Ma carte assignée']);
    $other = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte des autres']);
    $mine->members()->attach($member->id);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->set('filterMember', $member->id)
        ->assertSee('Ma carte assignée')
        ->assertDontSee('Carte des autres');
});

test('overdue filter shows only past-due incomplete cards', function () {
    ['board' => $board, 'owner' => $owner, 'a' => $a, 'b' => $b] = boardWithTwoCards();
    $a->update(['due_at' => now()->subDay()]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->set('filterDue', 'overdue')
        ->assertSee($a->title)
        ->assertDontSee($b->title);
});

test('reset filters restores all cards', function () {
    ['board' => $board, 'owner' => $owner, 'b' => $b] = boardWithTwoCards();

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->set('search', 'login')
        ->assertDontSee($b->title)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSee($b->title);
});

test('applyFilter casts values and clears on empty string', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('applyFilter', 'filterLabel', (string) $label->id)
        ->assertSet('filterLabel', $label->id)
        ->call('applyFilter', 'filterDue', 'overdue')
        ->assertSet('filterDue', 'overdue')
        ->call('applyFilter', 'filterLabel', '')
        ->assertSet('filterLabel', null);
});

test('a saved view captures and restores the current filters', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('search', 'login')
        ->set('filterLabel', $label->id)
        ->set('filterDue', 'overdue')
        ->set('newViewName', 'Mes urgents')
        ->call('saveView')
        ->assertHasNoErrors()
        ->assertSet('newViewName', '');

    $view = $board->views()->where('user_id', $owner->id)->firstOrFail();
    expect($view->name)->toBe('Mes urgents')
        ->and($view->filters['label'])->toBe($label->id)
        ->and($view->filters['due'])->toBe('overdue');

    $component->call('resetFilters')->assertSet('filterLabel', null)
        ->call('applyView', $view->id)
        ->assertSet('search', 'login')
        ->assertSet('filterLabel', $label->id)
        ->assertSet('filterDue', 'overdue');
});

test('a saved view can be deleted, but not another user view', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $mine = $board->views()->create(['user_id' => $owner->id, 'name' => 'À moi', 'filters' => []]);

    $stranger = User::factory()->create();
    $theirs = $board->views()->create(['user_id' => $stranger->id, 'name' => 'Pas à moi', 'filters' => []]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('deleteView', $mine->id)
        ->call('deleteView', $theirs->id);

    expect($board->views()->whereKey($mine->id)->exists())->toBeFalse()
        ->and($board->views()->whereKey($theirs->id)->exists())->toBeTrue();
});

test('the saved view name is required', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newViewName', '')
        ->call('saveView')
        ->assertHasErrors('newViewName');

    expect($board->views()->count())->toBe(0);
});
