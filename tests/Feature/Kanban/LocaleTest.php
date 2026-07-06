<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Livewire\Livewire;

test('a user can switch their locale and it persists', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('updateLocale', 'en')
        ->assertSet('locale', 'en');

    expect($user->fresh()->locale)->toBe('en');
});

test('an unsupported locale is rejected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('updateLocale', 'de')
        ->assertStatus(422);

    expect($user->fresh()->locale)->toBeNull();
});

test('activity type labels are translated per locale', function () {
    app()->setLocale('fr');
    expect(__('activity.checklist.item.toggled'))->toBe('a coché/décoché un élément');

    app()->setLocale('en');
    expect(__('activity.checklist.item.toggled'))->toBe('checked/unchecked an item')
        ->and(__('activity.card.created'))->toBe('created the card');

    app()->setLocale('es');
    expect(__('activity.card.created'))->toBe('creó la tarjeta');

    app()->setLocale('fr');
});

test('the set-locale middleware applies the user preference', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $owner->update(['locale' => 'en']);

    // Hitting an authenticated page should switch the app locale for the request.
    $this->actingAs($owner)->get('/boards/'.$board->public_id)->assertOk();
    expect(app()->getLocale())->toBe('en');
});
