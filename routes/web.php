<?php

use App\Livewire\Boards\Show as BoardShow;
use App\Livewire\Dashboard;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\Settings\Profile;
use App\Livewire\Workspaces\WorkspaceSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/boards/{board}', BoardShow::class)->name('boards.show');
    Route::get('/workspaces/{workspace}/settings', WorkspaceSettings::class)->name('workspaces.settings');
});

// Invitation links are reachable by guests: an invitee without an account is
// routed to (invite-gated) registration; existing users are asked to log in.
Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');
});
