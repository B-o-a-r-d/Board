<?php

use App\Enums\Permission;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Board;
use App\Models\Card;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, observer: User, card: Card}
 */
function boardWithObserver(): array
{
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $observer = User::factory()->create();
    $board->members()->attach($observer, ['role' => 'observer']);

    return compact('board', 'owner', 'observer', 'card');
}

test('a workspace seeds the four system roles on creation', function () {
    ['board' => $board] = makeCardContext();

    $keys = $board->workspace->roles()->orderBy('key')->pluck('key')->all();

    expect($keys)->toBe(['admin', 'member', 'observer', 'owner']);
});

test('system roles carry the expected permissions', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();
    $observer = User::factory()->create();
    $board->members()->attach($observer, ['role' => 'observer']);

    expect($board->userCan($owner, Permission::MemberManage))->toBeTrue()
        ->and($board->userCan($member, Permission::CardManage))->toBeTrue()
        ->and($board->userCan($member, Permission::CommentPost))->toBeTrue()
        ->and($board->userCan($member, Permission::MemberManage))->toBeFalse()
        ->and($board->userCan($observer, Permission::BoardView))->toBeTrue()
        ->and($board->userCan($observer, Permission::CardManage))->toBeFalse()
        ->and($board->userCan($observer, Permission::CommentPost))->toBeFalse();
});

test('an observer is forbidden from adding a card', function () {
    ['board' => $board, 'observer' => $observer] = boardWithObserver();
    $list = $board->lists()->firstOrFail();

    Livewire::actingAs($observer)->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", 'Interdit')
        ->call('addCard', $list->id)
        ->assertForbidden();

    expect($list->cards()->where('title', 'Interdit')->exists())->toBeFalse();
});

test('an observer can open a card to read but cannot edit it', function () {
    ['board' => $board, 'observer' => $observer, 'card' => $card] = boardWithObserver();

    // Editing the title (implicit save on change) is forbidden for a read-only observer.
    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->assertOk()
        ->set('title', 'Piraté')
        ->assertForbidden();

    expect($card->fresh()->title)->not->toBe('Piraté');
});

test('an observer cannot comment', function () {
    ['board' => $board, 'observer' => $observer, 'card' => $card] = boardWithObserver();

    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('addComment', 'Coucou')
        ->assertForbidden();

    expect($card->comments()->count())->toBe(0);
});

test('an observer cannot rename, recolor or delete a board label', function () {
    ['board' => $board, 'observer' => $observer, 'card' => $card] = boardWithObserver();
    $label = $board->labels()->create(['name' => 'Ancien', 'color' => '#ef4444']);

    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('renameLabel', $label->id, 'Piraté')
        ->assertForbidden();

    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('recolorLabel', $label->id, '#000000')
        ->assertForbidden();

    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('deleteLabel', $label->id)
        ->assertForbidden();

    $label->refresh();
    expect($label->name)->toBe('Ancien')
        ->and($label->color)->toBe('#ef4444')
        ->and($board->labels()->whereKey($label->id)->exists())->toBeTrue();
});

test('a member (contributor) can rename, recolor and delete a board label', function () {
    ['board' => $board, 'member' => $member, 'card' => $card] = makeCardContext();
    $label = $board->labels()->create(['name' => 'Ancien', 'color' => '#ef4444']);

    $component = Livewire::actingAs($member)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('renameLabel', $label->id, 'Nouveau')->assertHasNoErrors();
    expect($label->fresh()->name)->toBe('Nouveau');

    $component->call('recolorLabel', $label->id, '#22c55e')->assertHasNoErrors();
    expect($label->fresh()->color)->toBe('#22c55e');

    $component->call('deleteLabel', $label->id)->assertHasNoErrors();
    expect($board->labels()->whereKey($label->id)->exists())->toBeFalse();
});

test('recolor rejects a non-hex color to prevent CSS injection', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = $board->labels()->create(['name' => 'X', 'color' => '#ef4444']);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('recolorLabel', $label->id, 'red;background:url(//evil)')
        ->assertHasErrors('color');

    expect($label->fresh()->color)->toBe('#ef4444');
});

test('a member can still add a card', function () {
    ['board' => $board, 'member' => $member] = makeCardContext();
    $list = $board->lists()->firstOrFail();

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", 'OK membre')
        ->call('addCard', $list->id)
        ->assertHasNoErrors();

    expect($list->cards()->where('title', 'OK membre')->exists())->toBeTrue();
});

test('the board view renders read-only for an observer', function () {
    ['board' => $board, 'observer' => $observer] = boardWithObserver();

    Livewire::actingAs($observer)->test(Show::class, ['board' => $board])
        ->assertSee('Lecture seule')
        ->assertDontSee('+ Ajouter une carte')
        ->assertDontSee('+ Ajouter une liste');
});

test('the board view shows edit affordances to a member', function () {
    ['board' => $board, 'member' => $member] = makeCardContext();

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->assertDontSee('Lecture seule')
        ->assertSee('+ Ajouter une carte');
});

test('a custom role enforces its own permission set', function () {
    ['board' => $board] = makeCardContext();
    $reviewer = User::factory()->create();
    $board->workspace->roles()->create([
        'key' => 'reviewer',
        'name' => 'Relecteur',
        'permissions' => [Permission::BoardView->value, Permission::CommentPost->value],
        'is_system' => false,
        'position' => 10,
    ]);
    $board->members()->attach($reviewer, ['role' => 'reviewer']);

    expect($board->userCan($reviewer, Permission::CommentPost))->toBeTrue()
        ->and($board->userCan($reviewer, Permission::CardManage))->toBeFalse()
        ->and($board->userCan($reviewer, Permission::BoardView))->toBeTrue();
});
