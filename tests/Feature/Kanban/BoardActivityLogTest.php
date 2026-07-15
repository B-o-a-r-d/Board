<?php

use App\Enums\Role;
use App\Livewire\Boards\ActivityLog;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User}
 */
function makeActivityBoard(): array
{
    app()->setLocale('fr');

    $owner = User::factory()->create(['name' => 'Jibay Mcs']);
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return compact('board', 'owner');
}

test('creating a card logs an activity with the list context', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'À faire']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('newCardTitle.'.$list->id, 'Ma carte')
        ->call('addCard', $list->id);

    $activity = Activity::where('type', 'card.created')->latest('id')->firstOrFail();

    expect($activity->properties['card_title'])->toBe('Ma carte')
        ->and($activity->properties['list'])->toBe('À faire')
        ->and($activity->describe())->toBe('a ajouté Ma carte à À faire');
});

test('moving a card between lists logs from and to lists', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $from = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'En cours']);
    $to = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Terminé']);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $from->id, 'title' => 'Tâche']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('moveCardToList', $card->id, $to->id);

    $activity = Activity::where('type', 'card.moved')->latest('id')->firstOrFail();

    expect($activity->properties['from_list'])->toBe('En cours')
        ->and($activity->properties['to_list'])->toBe('Terminé')
        ->and($activity->describe())->toBe('a déplacé Tâche depuis En cours vers Terminé');
});

test('permanently deleting a card logs a detached deletion activity', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Backlog']);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'À jeter']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('deleteCardPermanently', $card->id);

    $activity = Activity::where('type', 'card.deleted')->latest('id')->firstOrFail();

    // The FK cascades, so a deletion log must not reference the card.
    expect($activity->card_id)->toBeNull()
        ->and($activity->properties['number'])->toBe($card->id)
        ->and($activity->properties['list'])->toBe('Backlog')
        ->and($activity->describe())->toBe('a supprimé la carte #'.$card->id.' de Backlog');
});

test('toggling completion logs both completed and uncompleted', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Feature']);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    $component->call('toggleComplete');
    expect(Activity::where('type', 'card.completed')->exists())->toBeTrue();

    $component->call('toggleComplete');
    $uncompleted = Activity::where('type', 'card.uncompleted')->latest('id')->firstOrFail();
    expect($uncompleted->describe())->toBe('a marqué Feature comme étant inachevée');
});

test('setting and clearing a due date logs the change', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id, 'due_at' => null]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('dueDate', '2026-08-01')->set('dueTime', '10:00')
        ->call('saveDates');

    expect(Activity::where('type', 'card.due_set')->exists())->toBeTrue();

    $component->call('clearDates');
    expect(Activity::where('type', 'card.due_removed')->exists())->toBeTrue();
});

test('a comment activity stores its comment id and focuses it', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Bien joué')
        ->call('addComment');

    $activity = Activity::where('type', 'comment.created')->latest('id')->firstOrFail();
    $comment = $card->comments()->firstOrFail();

    expect($activity->properties['comment_id'])->toBe($comment->id)
        ->and($activity->focusTarget())->toBe([
            'card' => $card->id,
            'section' => null,
            'comment' => $comment->id,
        ]);
});

test('an attachment activity focuses the attachments section', function () {
    ['board' => $board] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    $activity = Activity::create([
        'board_id' => $board->id,
        'card_id' => $card->id,
        'type' => 'attachment.added',
        'properties' => ['card_title' => $card->title, 'value' => 'photo.png'],
    ]);

    expect($activity->focusTarget())->toBe([
        'card' => $card->id,
        'section' => 'attachments',
        'comment' => null,
    ]);
});

test('a deleted-card activity is not clickable', function () {
    ['board' => $board] = makeActivityBoard();

    $activity = Activity::create([
        'board_id' => $board->id,
        'card_id' => null,
        'type' => 'card.deleted',
        'properties' => ['number' => 18, 'card_title' => 'X', 'list' => 'En cours'],
    ]);

    expect($activity->focusTarget()['card'])->toBeNull();
});

test('focusActivity closes the slide-over and opens the target card', function () {
    ['board' => $board, 'owner' => $owner] = makeActivityBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    Livewire::actingAs($owner)->test(ActivityLog::class, ['board' => $board])
        ->set('open', true)
        ->call('focusActivity', $card->id, 'attachments', null)
        ->assertSet('open', false)
        ->assertDispatched('open-card', cardId: $card->id, section: 'attachments', comment: null);
});
