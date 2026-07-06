<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('board.{board}', function (User $user, Board $board) {
    return Gate::forUser($user)->allows('view', $board);
});

Broadcast::channel('board-presence.{board}', function (User $user, Board $board) {
    if (! Gate::forUser($user)->allows('view', $board)) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
