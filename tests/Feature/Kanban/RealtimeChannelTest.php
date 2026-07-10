<?php

use App\Enums\Role;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;

/**
 * Broadcast channels are keyed by the integer board id (BoardActivity and the
 * client-side Echo subscriptions both use it). These guard against a regression
 * where HasPublicId::getRouteKeyName() = 'public_id' made channel model-binding
 * resolve by ULID, silently rejecting every subscription and killing realtime
 * (card moves, comments, everything routed through BoardActivity).
 *
 * @return array{board: Board, owner: User}
 */
function realtimeBoard(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return ['board' => $board, 'owner' => $owner];
}

/**
 * Pull a registered channel authorization callback straight off the broadcaster,
 * so we test the authorization logic without a Pusher/Reverb signer in the loop.
 */
function channelCallback(string $pattern): Closure
{
    $property = new ReflectionProperty(Broadcaster::class, 'channels');

    return $property->getValue(Broadcast::connection())[$pattern];
}

test('the board channel authorizes members by integer id and rejects the ULID', function () {
    ['board' => $board, 'owner' => $owner] = realtimeBoard();
    $authorize = channelCallback('board.{boardId}');

    expect($authorize($owner, (string) $board->id))->toBeTrue()
        ->and($authorize($owner, $board->public_id))->toBeFalse()
        ->and($authorize(User::factory()->create(), (string) $board->id))->toBeFalse();
});

test('the presence channel authorizes members by integer id and rejects outsiders', function () {
    ['board' => $board, 'owner' => $owner] = realtimeBoard();
    $authorize = channelCallback('board-presence.{boardId}');

    expect($authorize($owner, (string) $board->id))->toBeArray()
        ->and($authorize(User::factory()->create(), (string) $board->id))->toBeFalse();
});

test('the presence channel payload carries the user avatar url and biography', function () {
    ['board' => $board, 'owner' => $owner] = realtimeBoard();
    $owner->update(['avatar_path' => 'avatars/me.png', 'biography' => 'Chef de projet.']);
    $authorize = channelCallback('board-presence.{boardId}');

    $payload = $authorize($owner, (string) $board->id);

    expect($payload)->toMatchArray([
        'id' => $owner->id,
        'name' => $owner->name,
        'biography' => 'Chef de projet.',
        'guest' => false,
    ])->and($payload['avatar_url'])->toContain('/media/avatars/');
});
