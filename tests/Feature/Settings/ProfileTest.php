<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('profile page requires authentication', function () {
    $this->get('/profile')->assertRedirect('/login');
});

test('profile page is rendered for authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertOk()
        ->assertSeeLivewire(Profile::class);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'Nouveau Nom')
        ->set('email', 'nouveau@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nouveau Nom')
        ->and($user->email)->toBe('nouveau@example.com');
});

test('changing email resets verification status', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('email', 'nouveau@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is required and must be valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('email', 'not-an-email')
        ->call('updateProfileInformation')
        ->assertHasErrors('email');
});

test('password can be updated', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('current password must be correct to update password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});
