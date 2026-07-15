<?php

use App\Livewire\Boards\ListColumn;
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

// Card visibility under filters is rendered by the per-list ListColumn (which
// receives the filters as reactive props from Show), so it is asserted there.
test('text search filters cards (case-insensitive)', function () {
    ['board' => $board, 'owner' => $owner, 'list' => $list] = boardWithTwoCards();

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Corriger le login')
        ->assertSee('Refonte page accueil');

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'search' => 'LOGIN'])
        ->assertSee('Corriger le login')
        ->assertDontSee('Refonte page accueil');
});

test('label filter shows only cards with that label', function () {
    ['board' => $board, 'owner' => $owner, 'list' => $list, 'a' => $a, 'b' => $b] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $a->labels()->attach($label);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'filterLabels' => [$label->id]])
        ->assertSee($a->title)
        ->assertDontSee($b->title);
});

test('member filter shows only cards assigned to that member', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $mine = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Ma carte assignée']);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte des autres']);
    $mine->members()->attach($member->id);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'filterMembers' => [$member->id]])
        ->assertSee('Ma carte assignée')
        ->assertDontSee('Carte des autres');
});

test('overdue filter shows only past-due incomplete cards', function () {
    ['board' => $board, 'owner' => $owner, 'list' => $list, 'a' => $a, 'b' => $b] = boardWithTwoCards();
    $a->update(['due_at' => now()->subDay()]);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'filterDue' => 'overdue'])
        ->assertSee($a->title)
        ->assertDontSee($b->title);
});

test('reset filters restores all cards', function () {
    ['board' => $board, 'owner' => $owner, 'list' => $list, 'b' => $b] = boardWithTwoCards();

    // Filtered out of the column…
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'search' => 'login'])
        ->assertDontSee($b->title);

    // …resetFilters clears the state on Show…
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('search', 'login')
        ->call('resetFilters')
        ->assertSet('search', '');

    // …and with no filter the card shows again.
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee($b->title);
});

test('toggleLabel adds then removes an id and applyFilter sets the due filter', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('toggleLabel', $label->id)
        ->assertSet('filterLabels', [$label->id])
        ->call('toggleLabel', $label->id)
        ->assertSet('filterLabels', [])
        ->call('applyFilter', 'filterDue', 'overdue')
        ->assertSet('filterDue', 'overdue');
});

test('the label filter matches cards having ANY selected label', function () {
    ['board' => $board, 'owner' => $owner, 'list' => $list, 'a' => $a, 'b' => $b] = boardWithTwoCards();
    $l1 = Label::factory()->create(['board_id' => $board->id]);
    $l2 = Label::factory()->create(['board_id' => $board->id]);
    $a->labels()->attach($l1);
    $b->labels()->attach($l2);

    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'filterLabels' => [$l1->id, $l2->id]])
        ->assertSee($a->title)
        ->assertSee($b->title);
});

test('the "sans membre" filter shows only unassigned cards', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $assigned = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte assignée']);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte libre']);
    $assigned->members()->attach($member->id);

    // Filter state toggles on Show…
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('toggleUnassigned')
        ->assertSet('filterUnassigned', true);

    // …and the column shows only unassigned cards.
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list, 'filterUnassigned' => true])
        ->assertSee('Carte libre')
        ->assertDontSee('Carte assignée');
});

test('member and "sans membre" filters are mutually exclusive', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('toggleUnassigned')
        ->assertSet('filterUnassigned', true)
        ->call('toggleMember', $member->id)
        ->assertSet('filterUnassigned', false)
        ->assertSet('filterMembers', [$member->id])
        ->call('toggleUnassigned')
        ->assertSet('filterUnassigned', true)
        ->assertSet('filterMembers', []);
});

test('applyView migrates a legacy single-value saved view', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $view = $board->views()->create([
        'user_id' => $owner->id,
        'name' => 'Ancienne vue',
        'filters' => ['label' => $label->id, 'member' => $owner->id, 'due' => 'due'],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('applyView', $view->id)
        ->assertSet('filterLabels', [$label->id])
        ->assertSet('filterMembers', [$owner->id])
        ->assertSet('filterDue', 'due');
});

test('a saved view captures and restores the current filters', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $label = Label::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('search', 'login')
        ->call('toggleLabel', $label->id)
        ->set('filterDue', 'overdue')
        ->set('newViewName', 'Mes urgents')
        ->call('saveView')
        ->assertHasNoErrors()
        ->assertSet('newViewName', '');

    $view = $board->views()->where('user_id', $owner->id)->firstOrFail();
    expect($view->name)->toBe('Mes urgents')
        ->and($view->filters['labels'])->toBe([$label->id])
        ->and($view->filters['due'])->toBe('overdue');

    $component->call('resetFilters')->assertSet('filterLabels', [])
        ->call('applyView', $view->id)
        ->assertSet('search', 'login')
        ->assertSet('filterLabels', [$label->id])
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

test('a saved view remembers the calendar view type and restores it', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'calendar')
        ->set('newViewName', 'Planning')
        ->call('saveView')
        ->assertHasNoErrors();

    $view = $board->views()->where('user_id', $owner->id)->firstOrFail();
    expect($view->filters['view'])->toBe('calendar');

    $component->call('setView', 'board')->assertSet('view', 'board')
        ->call('applyView', $view->id)
        ->assertSet('view', 'calendar');
});

test('renameView renames only the current user view (and trims)', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $mine = $board->views()->create(['user_id' => $owner->id, 'name' => 'Ancien', 'filters' => []]);

    $stranger = User::factory()->create();
    $theirs = $board->views()->create(['user_id' => $stranger->id, 'name' => 'Intact', 'filters' => []]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('renameView', $mine->id, '  Nouveau nom  ')
        ->call('renameView', $theirs->id, 'Piraté');

    expect($mine->refresh()->name)->toBe('Nouveau nom')
        ->and($theirs->refresh()->name)->toBe('Intact');
});

test('renameView ignores a blank name', function () {
    ['board' => $board, 'owner' => $owner] = boardWithTwoCards();
    $view = $board->views()->create(['user_id' => $owner->id, 'name' => 'Garde', 'filters' => []]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('renameView', $view->id, '   ');

    expect($view->refresh()->name)->toBe('Garde');
});
