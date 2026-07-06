<?php

use App\Livewire\Invitations\AcceptInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Livewire\Livewire;

test('a guest invitee without an account is routed to registration', function () {
    $invitation = WorkspaceInvitation::factory()->create([
        'email' => 'newbie@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
        ->assertRedirect(route('register'));

    expect(session('invitation_token'))->toBe($invitation->token);
});

test('registration is blocked without an invitation when invite-only is on', function () {
    config(['board.registration_invite_only' => true]);

    $this->get('/register')->assertRedirect(route('login'));

    $this->post('/register', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();
});

test('an invited user can register and joins the workspace verified', function () {
    config(['board.registration_invite_only' => true]);

    $workspace = Workspace::factory()->create();
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'joiner@example.com',
        'role' => 'member',
        'accepted_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    $this->withSession(['invitation_token' => $invitation->token])->post('/register', [
        'name' => 'Joiner',
        'email' => 'joiner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'joiner@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($workspace->fresh()->hasMember($user))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('an invitee must register with the invited address', function () {
    config(['board.registration_invite_only' => true]);

    $invitation = WorkspaceInvitation::factory()->create([
        'email' => 'right@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    $this->withSession(['invitation_token' => $invitation->token])->post('/register', [
        'name' => 'X',
        'email' => 'wrong@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'wrong@example.com')->exists())->toBeFalse();
});

test('public registration still works when invite-only is off', function () {
    config(['board.registration_invite_only' => false]);

    $this->post('/register', [
        'name' => 'Free',
        'email' => 'free@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'free@example.com')->exists())->toBeTrue();
});
