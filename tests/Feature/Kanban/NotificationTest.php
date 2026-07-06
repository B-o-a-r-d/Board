<?php

use App\Livewire\Cards\CardDetail;
use App\Livewire\NotificationsBell;
use App\Notifications\CardNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('assigning a member notifies them', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleMember', $member->id);

    Notification::assertSentTo($member, CardNotification::class, fn ($n) => $n->type === 'assigned');
});

test('assigning yourself does not send a notification', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleMember', $owner->id);

    Notification::assertNotSentTo($owner, CardNotification::class);
});

test('commenting notifies assigned card members but not the author', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $card->members()->attach([$owner->id, $member->id]);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Un commentaire')
        ->call('addComment');

    Notification::assertSentTo($member, CardNotification::class, fn ($n) => $n->type === 'comment');
    Notification::assertNotSentTo($owner, CardNotification::class);
});

test('mentioning a member notifies them with a mention', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $member->update(['name' => 'Bob Martin']);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->set('newComment', 'Salut @bob-martin, regarde ça')
        ->call('addComment');

    Notification::assertSentTo($member, CardNotification::class, fn ($n) => $n->type === 'mention');
});

test('the due command notifies assigned members of soon-due cards', function () {
    Notification::fake();
    ['owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $card->update(['due_at' => now()->addHours(3)]);
    $card->members()->attach($member->id);

    $this->artisan('cards:notify-due')->assertSuccessful();

    Notification::assertSentTo($member, CardNotification::class, fn ($n) => $n->type === 'due_soon');
});

test('clicking a notification marks it read and redirects to its card', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $member->notify(new CardNotification($card, 'mention', $owner));
    $id = $member->notifications()->first()->id;

    Livewire::actingAs($member)
        ->test(NotificationsBell::class)
        ->call('openNotification', $id)
        ->assertRedirect(route('boards.show', ['board' => $board->id, 'card' => $card->id]));

    expect($member->fresh()->unreadNotifications()->count())->toBe(0);
});

test('deep-linking to a card opens its modal on the board', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.show', ['board' => $board, 'card' => $card]))
        ->assertOk()
        ->assertSee('Commentaires'); // this heading only renders inside the open card modal
});

test('a single notification can be toggled read then unread', function () {
    ['owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $member->notify(new CardNotification($card, 'assigned', $owner));
    $id = $member->notifications()->first()->id;

    $component = Livewire::actingAs($member)->test(NotificationsBell::class);

    $component->call('toggleRead', $id);
    expect($member->fresh()->unreadNotifications()->count())->toBe(0);

    $component->call('toggleRead', $id);
    expect($member->fresh()->unreadNotifications()->count())->toBe(1);
});

test('a single notification can be deleted and all can be cleared', function () {
    ['owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $member->notify(new CardNotification($card, 'assigned', $owner));
    $member->notify(new CardNotification($card, 'mention', $owner));
    $id = $member->notifications()->first()->id;

    $component = Livewire::actingAs($member)->test(NotificationsBell::class);

    $component->call('deleteNotification', $id);
    expect($member->fresh()->notifications()->count())->toBe(1);

    $component->call('deleteAll');
    expect($member->fresh()->notifications()->count())->toBe(0);
});

test('the bell lists notifications and marks them read', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();

    $member->notify(new CardNotification($card, 'assigned', $owner));

    expect($member->fresh()->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($member)
        ->test(NotificationsBell::class)
        ->assertSee($card->title)
        ->call('markAllRead');

    expect($member->fresh()->unreadNotifications()->count())->toBe(0);
});
