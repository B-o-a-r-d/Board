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

test('name and biography auto-save as the user types', function () {
    $user = User::factory()->create(['name' => 'Ancien']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', 'Nouveau Nom')
        ->set('biography', 'Développeur Laravel.')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nouveau Nom')
        ->and($user->biography)->toBe('Développeur Laravel.');
});

test('an empty biography is stored as null', function () {
    $user = User::factory()->create(['biography' => 'Quelque chose']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('biography', '')
        ->assertHasNoErrors();

    expect($user->fresh()->biography)->toBeNull();
});

test('the biography is capped at 500 characters', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('biography', str_repeat('a', 501))
        ->assertHasErrors('biography');

    expect($user->fresh()->biography)->toBeNull();
});

test('auto-save rejects an empty name', function () {
    $user = User::factory()->create(['name' => 'Garde']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('name', '')
        ->assertHasErrors('name');

    expect($user->fresh()->name)->toBe('Garde');
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
