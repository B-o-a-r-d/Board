<?php

use App\Http\Controllers\BoardExportController;
use App\Http\Controllers\PluginOAuthController;
use App\Http\Controllers\PublicBoardPresenceController;
use App\Livewire\Boards\PublicBoard;
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
    Route::get('/boards/{board:public_id}', BoardShow::class)->name('boards.show');
    Route::get('/boards/{board:public_id}/export/{format}', BoardExportController::class)->name('boards.export');
    Route::get('/workspaces/{workspace:public_id}/settings', WorkspaceSettings::class)->name('workspaces.settings');
});

// Invitation links are reachable by guests: an invitee without an account is
// routed to (invite-gated) registration; existing users are asked to log in.
Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');

// Public, read-only board share link (guest-accessible, resolved by token).
Route::get('/share/{token}', PublicBoard::class)->name('boards.public');
Route::post('/share/{token}/presence-auth', PublicBoardPresenceController::class)->name('boards.public.presence');

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');

    // Plugin (Power-Up) OAuth connection flow — one pair per provider.
    Route::get('/plugins/{boardPlugin}/oauth/github/redirect', [PluginOAuthController::class, 'githubRedirect'])
        ->name('plugins.oauth.github.redirect');
    Route::get('/plugins/oauth/github/callback', [PluginOAuthController::class, 'githubCallback'])
        ->name('plugins.oauth.github.callback');
});
