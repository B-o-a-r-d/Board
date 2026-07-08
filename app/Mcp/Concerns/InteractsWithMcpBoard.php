<?php

namespace App\Mcp\Concerns;

use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\Card;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait InteractsWithMcpBoard
{
    /**
     * Tools are only exposed while an admin has enabled the MCP master switch.
     */
    public function shouldRegister(Request $request): bool
    {
        return Setting::mcpEnabled();
    }

    /**
     * Resolve a model by its public ULID — the only identifier the MCP surface
     * accepts. The internal bigint primary key is never exposed nor accepted.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel|null
     */
    protected function resolvePublicId(string $modelClass, mixed $publicId): ?Model
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return $modelClass::query()->where('public_id', $publicId)->first();
    }

    /**
     * Origin marker stored on activities: 'mcp:<token/client name>'.
     */
    protected function mcpSource(Request $request): string
    {
        $name = $request->user()?->currentAccessToken()?->name ?? 'client';

        return 'mcp:'.$name;
    }

    /**
     * Ensure the user can view the board; return an error response if not.
     */
    protected function denyUnlessBoardAccess(Request $request, ?Board $board): ?Response
    {
        return $this->denyUnlessAbility($request, $board, 'view');
    }

    /**
     * Ensure the user may contribute (write cards/content) to the board.
     */
    protected function denyUnlessCanContribute(Request $request, ?Board $board): ?Response
    {
        return $this->denyUnlessAbility($request, $board, 'contribute');
    }

    /**
     * Ensure the user may comment on the board.
     */
    protected function denyUnlessCanComment(Request $request, ?Board $board): ?Response
    {
        return $this->denyUnlessAbility($request, $board, 'comment');
    }

    private function denyUnlessAbility(Request $request, ?Board $board, string $ability): ?Response
    {
        if (! $board || ! Gate::forUser($request->user())->allows($ability, $board)) {
            return Response::error('Ressource introuvable ou accès refusé.');
        }

        return null;
    }

    /**
     * Log an MCP-sourced activity and broadcast it live to board viewers,
     * exactly like a user action (so humans see AI changes in real time).
     *
     * @param  array<string, mixed>  $properties
     */
    protected function recordMcpActivity(Card $card, User $user, string $type, string $source, array $properties = []): void
    {
        Activity::create([
            'board_id' => $card->board_id,
            'card_id' => $card->id,
            'user_id' => $user->id,
            'type' => $type,
            'source' => $source,
            'properties' => $properties,
        ]);

        broadcast(new BoardActivity($card->board_id, $type, $user->id));
    }
}
