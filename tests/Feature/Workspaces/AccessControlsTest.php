<?php

use App\Enums\Permission;
use App\Livewire\Cards\CardDetail;
use App\Livewire\Workspaces\WorkspaceSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/* ----------------------------- Domain-restricted invitations ---------------------------- */

test('an invitation to a disallowed domain is blocked', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $board->workspace->update(['allowed_invite_domains' => ['example.com']]);

    Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $board->workspace])
        ->set('inviteEmail', 'intruder@other.io')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertHasErrors('inviteEmail');

    expect($board->workspace->invitations()->count())->toBe(0);
});

test('an invitation to an allowed domain succeeds', function () {
    Notification::fake();
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $board->workspace->update(['allowed_invite_domains' => ['example.com']]);

    Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $board->workspace])
        ->set('inviteEmail', 'newbie@example.com')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertHasNoErrors();

    expect($board->workspace->invitations()->where('email', 'newbie@example.com')->exists())->toBeTrue();
});

test('with no domain allow-list any email may be invited', function () {
    $board = makeCardContext()['board'];

    expect($board->workspace->invitationDomainAllowed('anyone@anywhere.tld'))->toBeTrue();
});

test('admins can save the access-control allow-lists as normalized arrays', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $board->workspace])
        ->set('allowedInviteDomains', '@Example.com, corp.IO , example.com')
        ->set('allowedAttachmentExtensions', '.PDF, png, .pdf')
        ->call('saveAccessControls')
        ->assertHasNoErrors();

    $board->workspace->refresh();
    expect($board->workspace->allowed_invite_domains)->toBe(['example.com', 'corp.io'])
        ->and($board->workspace->allowed_attachment_extensions)->toBe(['pdf', 'png']);
});

/* --------------------------------- Member deactivation ---------------------------------- */

test('a deactivated member loses access to the workspace boards', function () {
    ['board' => $board, 'member' => $member] = makeCardContext();
    $board->workspace->members()->attach($member, ['role' => 'member']);

    expect($board->userCan($member, Permission::CardManage))->toBeTrue()
        ->and(Gate::forUser($member)->allows('view', $board))->toBeTrue();

    $board->workspace->members()->updateExistingPivot($member->id, ['deactivated_at' => now()]);
    $board->workspace->refresh();

    expect($board->workspace->hasMember($member))->toBeFalse()
        ->and($board->workspace->memberIsDeactivated($member))->toBeTrue()
        ->and($board->userCan($member->fresh(), Permission::CardManage))->toBeFalse()
        ->and(Gate::forUser($member->fresh())->allows('view', $board))->toBeFalse();
});

test('an admin can deactivate and reactivate a member via settings', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();
    $board->workspace->members()->attach($member, ['role' => 'member']);

    $component = Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $board->workspace])
        ->call('deactivateMember', $member->id);

    expect($board->workspace->memberIsDeactivated($member))->toBeTrue();

    $component->call('reactivateMember', $member->id);

    expect($board->workspace->fresh()->memberIsDeactivated($member))->toBeFalse();
});

test('the owner cannot be deactivated', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(WorkspaceSettings::class, ['workspace' => $board->workspace])
        ->call('deactivateMember', $owner->id);

    expect($board->workspace->memberIsDeactivated($owner))->toBeFalse();
});

/* ------------------------------ Attachment type restrictions ---------------------------- */

test('an attachment with a disallowed extension is rejected', function () {
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $board->workspace->update(['allowed_attachment_extensions' => ['png']]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board->fresh()])
        ->call('openCard', $card->id)
        ->set('upload', UploadedFile::fake()->image('photo.jpg'))
        ->call('saveAttachment')
        ->assertHasErrors('upload');

    expect($card->attachments()->count())->toBe(0);
});

test('an attachment with an allowed extension is accepted', function () {
    Storage::fake('local');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $board->workspace->update(['allowed_attachment_extensions' => ['png']]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board->fresh()])
        ->call('openCard', $card->id)
        ->set('upload', UploadedFile::fake()->image('photo.png'))
        ->call('saveAttachment')
        ->assertHasNoErrors();

    expect($card->attachments()->count())->toBe(1);
});

test('with no attachment allow-list any permitted file type is accepted', function () {
    $board = makeCardContext()['board'];

    expect($board->workspace->attachmentExtensionAllowed('exe'))->toBeTrue();
});
