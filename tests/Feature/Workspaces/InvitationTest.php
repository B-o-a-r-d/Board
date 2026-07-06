<?php

use App\Enums\Role;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\Workspaces\WorkspaceSettings;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * @return array{0: Workspace, 1: User}
 */
function workspaceWithOwner(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);

    return [$workspace, $owner];
}

test('an admin can invite a member by email', function () {
    Notification::fake();
    [$workspace, $owner] = workspaceWithOwner();

    Livewire::actingAs($owner)
        ->test(WorkspaceSettings::class, ['workspace' => $workspace])
        ->set('inviteEmail', 'nouveau@example.com')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertHasNoErrors();

    expect($workspace->invitations()->where('email', 'nouveau@example.com')->exists())->toBeTrue();
    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
});

test('a plain member cannot invite', function () {
    [$workspace] = workspaceWithOwner();
    $member = User::factory()->create();
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)
        ->test(WorkspaceSettings::class, ['workspace' => $workspace])
        ->set('inviteEmail', 'x@example.com')
        ->call('invite')
        ->assertForbidden();
});

test('inviting an existing member is rejected', function () {
    [$workspace, $owner] = workspaceWithOwner();
    $member = User::factory()->create(['email' => 'bob@example.com']);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($owner)
        ->test(WorkspaceSettings::class, ['workspace' => $workspace])
        ->set('inviteEmail', 'bob@example.com')
        ->call('invite')
        ->assertHasErrors('inviteEmail');
});

test('accepting an invitation adds the user as a member', function () {
    [$workspace] = workspaceWithOwner();
    $invitee = User::factory()->create(['email' => 'joiner@example.com']);
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'joiner@example.com',
        'role' => 'member',
        'accepted_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    Livewire::actingAs($invitee)
        ->test(AcceptInvitation::class, ['token' => $invitation->token])
        ->call('accept')
        ->assertRedirect(route('workspaces.settings', $workspace));

    expect($workspace->fresh()->hasMember($invitee))->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and($workspace->memberRole($invitee))->toBe(Role::Member);
});

test('an invitation addressed to another email cannot be accepted', function () {
    [$workspace] = workspaceWithOwner();
    $other = User::factory()->create(['email' => 'other@example.com']);
    $invitation = WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'target@example.com',
        'accepted_at' => null,
        'expires_at' => now()->addDay(),
    ]);

    Livewire::actingAs($other)
        ->test(AcceptInvitation::class, ['token' => $invitation->token])
        ->call('accept');

    expect($workspace->fresh()->hasMember($other))->toBeFalse();
});

test('an expired invitation cannot be accepted', function () {
    [$workspace] = workspaceWithOwner();
    $invitee = User::factory()->create(['email' => 'late@example.com']);
    $invitation = WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'email' => 'late@example.com',
        'accepted_at' => null,
    ]);

    Livewire::actingAs($invitee)
        ->test(AcceptInvitation::class, ['token' => $invitation->token])
        ->call('accept');

    expect($workspace->fresh()->hasMember($invitee))->toBeFalse();
});

test('an admin can change a member role and remove them', function () {
    [$workspace, $owner] = workspaceWithOwner();
    $member = User::factory()->create();
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    $component = Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $workspace]);

    $component->call('updateRole', $member->id, 'admin');
    expect($workspace->memberRole($member))->toBe(Role::Admin);

    $component->call('removeMember', $member->id);
    expect($workspace->fresh()->hasMember($member))->toBeFalse();
});

test('the owner cannot be demoted or removed', function () {
    [$workspace, $owner] = workspaceWithOwner();
    $admin = User::factory()->create();
    $workspace->members()->attach($admin, ['role' => Role::Admin->value]);

    $component = Livewire::actingAs($admin)->test(WorkspaceSettings::class, ['workspace' => $workspace]);

    $component->call('updateRole', $owner->id, 'member');
    expect($workspace->memberRole($owner))->toBe(Role::Owner);

    $component->call('removeMember', $owner->id);
    expect($workspace->fresh()->hasMember($owner))->toBeTrue();
});
