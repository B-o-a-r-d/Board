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

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');
    Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');
});
