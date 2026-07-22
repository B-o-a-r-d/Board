<?php

use App\Http\Controllers\BoardExportController;
use App\Http\Controllers\BoardIcalController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PluginAssetController;
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

// Board-scoped media served from a private disk. Authorized in the controller:
// board members (policy `view`) or guests presenting the board share token.
Route::get('/media/cards/{card}/cover', [MediaController::class, 'cardCover'])->name('media.card-cover');
Route::get('/media/lists/{list}/cover', [MediaController::class, 'listCover'])->name('media.list-cover');
Route::get('/media/boards/{board}/background', [MediaController::class, 'boardBackground'])->name('media.board-background');

// Public, read-only iCal feeds (calendar apps subscribe without auth; signed token).
Route::get('/calendar/board/{token}.ics', BoardIcalController::class)->where('token', '[A-Za-z0-9]+')->name('boards.ical');
Route::get('/calendar/user/{token}.ics', UserIcalController::class)->where('token', '[A-Za-z0-9]+')->name('calendar.ical');

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');

    // Attachments (members only) and avatars (any authenticated user), streamed
    // from a private disk with anti-XSS headers by MediaController.
    Route::get('/media/attachments/{attachment}', [MediaController::class, 'attachment'])->name('attachments.show');
    Route::get('/media/avatars/{user}', [MediaController::class, 'avatar'])->name('media.avatar');

    // Plugin (Power-Up) OAuth connection flow — one provider-agnostic broker,
    // driven by the plugin's ProvidesOAuth declaration.
    Route::get('/plugins/{boardPlugin}/oauth/redirect', [PluginOAuthController::class, 'redirect'])
        ->name('plugins.oauth.redirect');
    Route::get('/plugins/oauth/callback', [PluginOAuthController::class, 'callback'])
        ->name('plugins.oauth.callback');

    // Pre-built front-end assets a plugin ships (ProvidesAssets), served from
    // the install volume with an immutable hash-versioned cache.
    Route::get('/plugins/{plugin}/assets/{file}', PluginAssetController::class)
        ->name('plugins.asset');
});
