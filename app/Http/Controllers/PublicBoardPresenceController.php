<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicBoardPresenceController extends Controller
{
    /**
     * Sign a Reverb (Pusher protocol) presence-channel auth response for an
     * anonymous guest, so viewers of a public board can appear "à la Google
     * Docs" without an account. Each session gets a stable random identity.
     */
    public function __invoke(Request $request, string $token): JsonResponse
    {
        abort_unless((bool) config('board.public_sharing'), 404);

        $board = Board::query()
            ->whereNull('archived_at')
            ->where('share_token', $token)
            ->firstOrFail();

        $socketId = (string) $request->input('socket_id');
        $channelName = (string) $request->input('channel_name');

        // Guests join the board's own presence channel so authenticated members
        // see them too. The share token proves they may join this board.
        abort_unless($channelName === 'presence-board-presence.'.$board->id, 403);

        $identity = $this->anonymousIdentity($request);

        $channelData = json_encode([
            'user_id' => $identity['id'],
            'user_info' => [
                'id' => $identity['id'],
                'name' => $identity['name'],
                'color' => $identity['color'],
                'guest' => true,
            ],
        ]);

        $secret = (string) config('broadcasting.connections.reverb.secret');
        $key = (string) config('broadcasting.connections.reverb.key');

        $signature = hash_hmac('sha256', $socketId.':'.$channelName.':'.$channelData, $secret);

        return response()->json([
            'auth' => $key.':'.$signature,
            'channel_data' => $channelData,
        ]);
    }

    /**
     * @return array{id: string, name: string, color: string}
     */
    private function anonymousIdentity(Request $request): array
    {
        if (! $request->session()->has('public_viewer_identity')) {
            $animals = ['Renard', 'Loutre', 'Hibou', 'Panda', 'Koala', 'Lynx', 'Héron', 'Castor', 'Faucon', 'Blaireau', 'Écureuil', 'Colibri'];
            $colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'];

            $request->session()->put('public_viewer_identity', [
                'id' => (string) Str::uuid(),
                'name' => __('Visiteur').' '.$animals[array_rand($animals)],
                'color' => $colors[array_rand($colors)],
            ]);
        }

        /** @var array{id: string, name: string, color: string} */
        return $request->session()->get('public_viewer_identity');
    }
}
