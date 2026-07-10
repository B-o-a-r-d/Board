<?php

use App\Automations\Actions\AddChecklistAction;
use App\Automations\Actions\ArchiveListCardsAction;
use App\Automations\Actions\AssignMemberAction;
use App\Automations\Actions\ClearDueDateAction;
use App\Automations\Actions\CopyCardAction;
use App\Automations\Actions\CreateCardAction;
use App\Automations\Actions\CreateFollowUpCardAction;
use App\Automations\Actions\MarkIncompleteAction;
use App\Automations\Actions\MoveInListAction;
use App\Automations\Actions\MoveToListAction;
use App\Automations\Actions\NotifyMembersAction;
use App\Automations\Actions\PostCommentAction;
use App\Automations\Actions\RemoveLabelAction;
use App\Automations\Actions\SendWebhookAction;
use App\Automations\Actions\SetCustomFieldAction;
use App\Automations\Actions\SetDueDateAction;
use App\Automations\Actions\SortListAction;
use App\Automations\Actions\UnassignMemberAction;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardLink;
use App\Models\CustomField;
use App\Models\Label;
use App\Notifications\CardNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** Phase 3: one effect test per action of the catalog. */
test('move_to_list can drop the card at the top of the target list', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id]);
    $existing = Card::factory()->create(['board_list_id' => $target->id, 'board_id' => $board->id, 'position' => 0]);

    (new MoveToListAction)->run($card, ['list_id' => $target->id, 'position' => 'top']);

    expect($card->fresh()->board_list_id)->toBe($target->id)
        ->and($card->fresh()->position)->toBe(0)
        ->and($existing->fresh()->position)->toBe(1);
});

test('move_in_list reorders the card inside its own list', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $sibling = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'position' => 5]);

    (new MoveInListAction)->run($card, ['position' => 'bottom']);
    expect($card->fresh()->position)->toBe(6);

    (new MoveInListAction)->run($card, ['position' => 'top']);
    expect($card->fresh()->position)->toBe(0)
        ->and($sibling->fresh()->position)->toBe(6);
});

test('sort_list orders the cards by due date, undated last', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $card->update(['due_at' => now()->addDays(5)]);
    $soon = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'due_at' => now()->addDay(), 'position' => 9]);
    $undated = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id, 'position' => 1]);

    (new SortListAction)->run($card, ['by' => 'due']);

    expect($soon->fresh()->position)->toBe(0)
        ->and($card->fresh()->position)->toBe(1)
        ->and($undated->fresh()->position)->toBe(2);
});

test('archive_list_cards archives every live card of the list', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $other = Card::factory()->create(['board_list_id' => $card->board_list_id, 'board_id' => $board->id]);

    (new ArchiveListCardsAction)->run($card, []);

    expect($card->fresh()->archived_at)->not->toBeNull()
        ->and($other->fresh()->archived_at)->not->toBeNull();
});

test('remove_label detaches the label, assign/unassign_member support "me"', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $card->labels()->attach($label);

    (new RemoveLabelAction)->run($card, ['label_id' => $label->id]);
    expect($card->labels()->count())->toBe(0);

    $this->actingAs($owner);

    (new AssignMemberAction)->run($card, ['user_id' => 'me']);
    expect($card->members()->whereKey($owner->id)->exists())->toBeTrue();

    (new UnassignMemberAction)->run($card, ['user_id' => 'me']);
    expect($card->members()->count())->toBe(0);
});

test('add_checklist creates the checklist with its templated items', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();

    (new AddChecklistAction)->run($card, ['title' => 'QA', 'items' => "Relire, Tester\nDéployer"]);

    $checklist = $card->checklists()->first();
    expect($checklist->title)->toBe('QA')
        ->and($checklist->items()->orderBy('position')->pluck('content')->all())->toBe(['Relire', 'Tester', 'Déployer']);
});

test('create_card respects the unique option', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $this->actingAs($owner);
    $action = new CreateCardAction;

    $action->run($card, ['title' => 'Standup', 'unique' => true]);
    $action->run($card, ['title' => 'Standup', 'unique' => true]);
    expect(Card::where('title', 'Standup')->count())->toBe(1);

    $action->run($card, ['title' => 'Standup']);
    expect(Card::where('title', 'Standup')->count())->toBe(2);
});

test('copy_card clones the card with labels, members and due date', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $this->actingAs($owner);
    $target = BoardList::factory()->create(['board_id' => $board->id]);
    $label = Label::factory()->create(['board_id' => $board->id]);
    $card->labels()->attach($label);
    $card->members()->attach($member);
    $card->update(['due_at' => now()->addDays(2)]);

    (new CopyCardAction)->run($card, ['list_id' => $target->id]);

    $copy = $target->cards()->first();
    // The factory keeps a Stringable in memory — compare the persisted string.
    expect($copy->title)->toBe((string) $card->title)
        ->and($copy->labels()->pluck('labels.id')->all())->toBe([$label->id])
        ->and($copy->members()->pluck('users.id')->all())->toBe([$member->id])
        ->and($copy->due_at)->not->toBeNull();
});

test('create_follow_up_card creates a related card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $this->actingAs($owner);

    (new CreateFollowUpCardAction)->run($card, []);

    $followUp = Card::where('title', 'like', 'Suivi : %')->first();
    expect($followUp)->not->toBeNull()
        ->and(CardLink::where('type', 'relates_to')
            ->where('card_id', min($card->id, $followUp->id))
            ->where('related_card_id', max($card->id, $followUp->id))
            ->exists())->toBeTrue();
});

test('set_due_date, clear_due_date and mark_incomplete adjust the schedule', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $card->update(['completed_at' => now()]);

    (new SetDueDateAction)->run($card, ['days' => 3, 'time' => '09:30']);
    $due = $card->fresh()->due_at;
    expect($due->isSameDay(now()->addDays(3)))->toBeTrue()
        ->and($due->format('H:i'))->toBe('09:30');

    (new ClearDueDateAction)->run($card, []);
    expect($card->fresh()->due_at)->toBeNull();

    (new MarkIncompleteAction)->run($card, []);
    expect($card->fresh()->completed_at)->toBeNull();
});

test('post_comment renders the template and skips without an author', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Paiement']);

    // Userless (scheduled) context: nothing happens.
    (new PostCommentAction)->run($card, ['body' => 'Bonjour {card}']);
    expect($card->comments()->count())->toBe(0);

    $this->actingAs($owner);
    (new PostCommentAction)->run($card, ['body' => '{card} est prête dans {list}']);

    expect($card->comments()->first()->body)->toBe('Paiement est prête dans '.$card->list->name);
});

test('set_custom_field coerces by type and clears on empty', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $field = CustomField::create(['board_id' => $board->id, 'name' => 'Statut', 'type' => 'select', 'options' => ['Ouvert', 'Fermé'], 'position' => 0]);
    $action = new SetCustomFieldAction;

    $action->run($card, ['field_id' => $field->id, 'value' => 'Hors liste']);
    expect($card->customFieldValues()->count())->toBe(0);

    $action->run($card, ['field_id' => $field->id, 'value' => 'Fermé']);
    expect($card->customFieldValues()->first()->value)->toBe('Fermé');

    $action->run($card, ['field_id' => $field->id, 'value' => '']);
    expect($card->customFieldValues()->count())->toBe(0);
});

test('notify_members notifies members and watchers except the actor', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    Notification::fake();
    $this->actingAs($owner);
    $card->members()->attach([$owner->id, $member->id]);

    (new NotifyMembersAction)->run($card, ['message' => 'À vérifier']);

    Notification::assertSentTo($member, CardNotification::class);
    Notification::assertNotSentTo($owner, CardNotification::class);
});

test('send_webhook posts a signed payload to a safe URL only', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    Http::fake();

    // Public IP literal: no DNS needed, passes the SSRF gate; request is faked.
    (new SendWebhookAction)->run($card, ['url' => 'https://8.8.8.8/hook', 'secret' => 's3cret']);

    Http::assertSent(function ($request) use ($card) {
        return str_starts_with($request->header('X-Board-Signature')[0] ?? '', 'sha256=')
            && $request['card']['id'] === $card->public_id;
    });

    // Internal/reserved hosts are refused before any request goes out.
    Http::fake();
    expect(fn () => (new SendWebhookAction)->run($card, ['url' => 'http://169.254.169.254/latest/meta-data']))
        ->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});
