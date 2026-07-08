<?php

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Broadcast channels are an internal pub/sub transport keyed by the integer id
// (that is what BoardActivity and the client-side Echo subscriptions use). They
// must NOT resolve via route-model binding: HasPublicId::getRouteKeyName() now
// binds public routes by the ULID, so implicit binding here would look the board
// up by public_id and reject every subscription. Resolve by primary key instead.
Broadcast::channel('board.{boardId}', function (User $user, string $boardId) {
    $board = Board::find($boardId);

    return $board !== null && Gate::forUser($user)->allows('view', $board);
});

Broadcast::channel('board-presence.{boardId}', function (User $user, string $boardId) {
    $board = Board::find($boardId);

    if (! $board || ! Gate::forUser($user)->allows('view', $board)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatarUrl(),
        'biography' => $user->biography,
        'guest' => false,
    ];
});
