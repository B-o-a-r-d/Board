<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Jean Test',
        'email' => 'jean@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));

    expect(User::where('email', 'jean@example.com')->exists())->toBeTrue();
});

test('registration requires matching password confirmation', function () {
    $this->post('/register', [
        'name' => 'Jean Test',
        'email' => 'jean@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});
