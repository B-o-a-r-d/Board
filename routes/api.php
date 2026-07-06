<?php

use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\BoardListController;
use App\Http\Controllers\Api\V1\CardController;
use App\Http\Controllers\Api\V1\LabelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::prefix('v1')->name('api.v1.')->group(function () {
        // Boards
        Route::apiResource('boards', BoardController::class);

        // Lists (nested for index/store, shallow for update/destroy)
        Route::get('boards/{board}/lists', [BoardListController::class, 'index'])->name('boards.lists.index');
        Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('boards.lists.store');
        Route::match(['put', 'patch'], 'lists/{list}', [BoardListController::class, 'update'])->name('lists.update');
        Route::delete('lists/{list}', [BoardListController::class, 'destroy'])->name('lists.destroy');

        // Cards
        Route::get('lists/{list}/cards', [CardController::class, 'index'])->name('lists.cards.index');
        Route::post('lists/{list}/cards', [CardController::class, 'store'])->name('lists.cards.store');
        Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
        Route::match(['put', 'patch'], 'cards/{card}', [CardController::class, 'update'])->name('cards.update');
        Route::post('cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
        Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

        // Labels
        Route::get('boards/{board}/labels', [LabelController::class, 'index'])->name('boards.labels.index');
        Route::post('boards/{board}/labels', [LabelController::class, 'store'])->name('boards.labels.store');
        Route::match(['put', 'patch'], 'labels/{label}', [LabelController::class, 'update'])->name('labels.update');
        Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
    });
});
