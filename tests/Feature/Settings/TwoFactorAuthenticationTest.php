<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

/** Fully enable + confirm 2FA for a user, returning the fresh model. */
function enrollTwoFactor(User $user): User
{
    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    app(ConfirmTwoFactorAuthentication::class)($user, $code);

    return $user->refresh();
}

test('a user can enable and confirm two-factor authentication', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Profile::class)
        ->call('enableTwoFactorAuthentication')
        ->assertSet('showingQrCode', true);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    $component->set('twoFactorCode', $code)
        ->call('confirmTwoFactorAuthentication')
        ->assertHasNoErrors()
        ->assertSet('twoFactorEnabled', true)
        ->assertSet('showingRecoveryCodes', true);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('confirming with an invalid code fails and leaves 2FA unconfirmed', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('enableTwoFactorAuthentication')
        ->set('twoFactorCode', '000000')
        ->call('confirmTwoFactorAuthentication')
        ->assertHasErrors('code')
        ->assertSet('twoFactorEnabled', false);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

test('a user can disable two-factor authentication', function () {
    $user = enrollTwoFactor(User::factory()->create());

    Livewire::actingAs($user)->test(Profile::class)
        ->assertSet('twoFactorEnabled', true)
        ->call('disableTwoFactorAuthentication')
        ->assertSet('twoFactorEnabled', false);

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('regenerating recovery codes replaces the previous set', function () {
    $user = enrollTwoFactor(User::factory()->create());
    $before = $user->recoveryCodes();

    Livewire::actingAs($user)->test(Profile::class)
        ->call('regenerateRecoveryCodes')
        ->assertSet('showingRecoveryCodes', true);

    expect($user->refresh()->recoveryCodes())
        ->toHaveCount(count($before))
        ->not->toBe($before);
});

test('a two-factor-enabled user is challenged for a code on login', function () {
    $user = enrollTwoFactor(User::factory()->create());

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    expect(session()->has('login.id'))->toBeTrue();
    $this->assertGuest();
});

test('a user without two-factor logs in directly', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});
