<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Assign or unassign a board member on a card. Set "assigned" to false to remove them.')]
class AssignMemberTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|integer',
            'user_id' => 'required|integer',
            'assigned' => 'sometimes|boolean',
        ]);

        $card = Card::find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $userId = (int) $request->get('user_id');

        if (! $card->board->members()->whereKey($userId)->exists()) {
            return Response::error('Cet utilisateur n\'est pas membre du board.');
        }

        $assigned = $request->has('assigned') ? $request->boolean('assigned') : true;

        if ($assigned) {
            $card->members()->syncWithoutDetaching([$userId]);
        } else {
            $card->members()->detach([$userId]);
        }

        $this->recordMcpActivity($card, $request->user(), 'card.members', $this->mcpSource($request), ['user_id' => $userId]);

        return Response::json(['card_id' => $card->id, 'user_id' => $userId, 'assigned' => $assigned]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id.')->required(),
            'user_id' => $schema->integer()->description('The board member user id.')->required(),
            'assigned' => $schema->boolean()->description('true to assign (default), false to unassign.'),
        ];
    }
}
