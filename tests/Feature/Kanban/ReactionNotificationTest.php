<?php

use App\Livewire\Cards\CardDetail;
use App\Livewire\Settings\Profile;
use App\Models\User;
use App\Notifications\CardNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a reaction is toggled on a comment and notifies the author', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $comment = $card->comments()->create(['user_id' => $owner->id, 'body' => 'Salut']);

    Livewire::actingAs($member)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleReaction', $comment->id, '👍');

    expect($comment->reactions()->where('user_id', $member->id)->where('emoji', '👍')->exists())->toBeTrue();
    Notification::assertSentTo($owner, CardNotification::class);

    // Toggling the same emoji again removes it.
    Livewire::actingAs($member)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleReaction', $comment->id, '👍');

    expect($comment->reactions()->count())->toBe(0);
});

test('reacting to your own comment does not notify', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $comment = $card->comments()->create(['user_id' => $owner->id, 'body' => 'Moi']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleReaction', $comment->id, '🎉');

    Notification::assertNothingSent();
});

test('an emoji outside the curated set is rejected', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $comment = $card->comments()->create(['user_id' => $owner->id, 'body' => 'Hi']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleReaction', $comment->id, '💩')
        ->assertStatus(422);

    expect($comment->reactions()->count())->toBe(0);
});

test('notification preferences are saved from the profile', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('updateNotificationPreference', 'email', true)
        ->call('updateNotificationPreference', 'comments', false);

    $prefs = $user->fresh()->notificationPreferences();
    expect($prefs['email'])->toBeTrue()->and($prefs['comments'])->toBeFalse();
});

test('via() respects per-event and channel preferences', function () {
    ['card' => $card, 'owner' => $actor] = makeCardContext();

    $default = User::factory()->create();
    expect((new CardNotification($card, 'comment', $actor))->via($default))->toBe(['database', 'broadcast']);

    $emailer = User::factory()->create(['notification_preferences' => ['email' => true]]);
    expect((new CardNotification($card, 'comment', $actor))->via($emailer))->toContain('mail');

    $noReactions = User::factory()->create(['notification_preferences' => ['reactions' => false]]);
    expect((new CardNotification($card, 'reaction', $actor))->via($noReactions))->toBe([]);

    $inappOff = User::factory()->create(['notification_preferences' => ['inapp' => false]]);
    expect((new CardNotification($card, 'mention', $actor))->via($inappOff))->toBe([]);
});

test('mentions-only mode drops plain comments but keeps mentions', function () {
    ['card' => $card, 'owner' => $actor] = makeCardContext();
    $user = User::factory()->create(['notification_preferences' => ['mentions_only' => true]]);

    expect((new CardNotification($card, 'comment', $actor))->via($user))->toBe([])
        ->and((new CardNotification($card, 'mention', $actor))->via($user))->toBe(['database', 'broadcast']);
});

test('watching a card is toggled on and off', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleWatch');
    expect($card->watchers()->whereKey($owner->id)->exists())->toBeTrue();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleWatch');
    expect($card->watchers()->count())->toBe(0);
});

test('commenting subscribes you to the card', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Je note')
        ->call('addComment');

    expect($card->watchers()->whereKey($owner->id)->exists())->toBeTrue();
});

test('a watcher who is not a card member is notified of comments', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $card->watchers()->attach($member->id);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Nouveau commentaire')
        ->call('addComment');

    Notification::assertSentTo($member, CardNotification::class);
    expect($card->members()->whereKey($member->id)->exists())->toBeFalse();
});

test('a board member who neither watches nor is assigned is not notified of comments', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Coucou')
        ->call('addComment');

    Notification::assertNotSentTo($member, CardNotification::class);
});
