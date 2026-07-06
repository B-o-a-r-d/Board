<?php

use App\Livewire\Boards\Show as BoardShow;
use App\Livewire\Dashboard;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/boards/{board}', BoardShow::class)->name('boards.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', Profile::class)->name('profile.edit');
});
