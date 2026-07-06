<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a comment to a card as the authenticated user.')]
class AddCommentTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|string',
            'body' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if (! $card || ! Gate::forUser($user)->allows('view', $card->board)) {
            return Response::error('Carte introuvable ou accès refusé.');
        }

        $comment = $card->comments()->create([
            'user_id' => $user->id,
            'body' => $request->get('body'),
        ]);

        $this->recordMcpActivity($card, $user, 'comment.created', $this->mcpSource($request));

        return Response::json(['id' => $comment->public_id, 'card_id' => $card->public_id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID) to comment on.')->required(),
            'body' => $schema->string()->description('The comment body.')->required(),
        ];
    }
}
