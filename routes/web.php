<?php

use App\Http\Controllers\BoardExportController;
use App\Http\Controllers\BoardIcalController;
use App\Http\Controllers\PluginOAuthController;
use App\Http\Controllers\PublicBoardPresenceController;
use App\Http\Controllers\UserIcalController;
use App\Livewire\Boards\PublicBoard;
use App\Livewire\Boards\Show as BoardShow;
use App\Livewire\Dashboard;
use App\Livewire\Invitations\AcceptInvitation;
use App\Livewire\Settings\Profile;
use App\Livewire\Workspaces\Roles as WorkspaceRoles;
use App\Livewire\Workspaces\Views as WorkspaceViews;
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
    Route::get('/workspaces/{workspace:public_id}/roles', WorkspaceRoles::class)->name('workspaces.roles');
    Route::get('/workspaces/{workspace:public_id}/calendar', WorkspaceViews::class)->defaults('view', 'calendar')->name('workspaces.calendar');
    Route::get('/workspaces/{workspace:public_id}/table', WorkspaceViews::class)->defaults('view', 'table')->name('workspaces.table');
});

// Invitation links are reachable by guests: an invitee without an account is
// routed to (invite-gated) registration; existing users are asked to log in.
Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');

// Public, read-only board share link (guest-accessible, resolved by token).
Route::get('/share/{token}', PublicBoard::class)->name('boards.public');
Route::post('/share/{token}/presence-auth', PublicBoardPresenceController::class)->name('boards.public.presence');

// Public, read-only iCal feeds (calendar apps subscribe without auth; signed token).
Route::get('/calendar/board/{token}.ics', BoardIcalController::class)->where('token', '[A-Za-z0-9]+')->name('boards.ical');
Route::get('/calendar/user/{token}.ics', UserIcalController::class)->where('token', '[A-Za-z0-9]+')->name('calendar.ical');

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');

    // Plugin (Power-Up) OAuth connection flow — one provider-agnostic broker,
    // driven by the plugin's ProvidesOAuth declaration.
    Route::get('/plugins/{boardPlugin}/oauth/redirect', [PluginOAuthController::class, 'redirect'])
        ->name('plugins.oauth.redirect');
    Route::get('/plugins/oauth/callback', [PluginOAuthController::class, 'callback'])
        ->name('plugins.oauth.callback');
});
