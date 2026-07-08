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
            'card_id' => 'required|string',
            'user_id' => 'required|string',
            'assigned' => 'sometimes|boolean',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        $member = $card->board->members()->where('users.public_id', $request->get('user_id'))->first();

        if (! $member) {
            return Response::error('Cet utilisateur n\'est pas membre du board.');
        }

        $assigned = $request->has('assigned') ? $request->boolean('assigned') : true;

        if ($assigned) {
            $card->members()->syncWithoutDetaching([$member->id]);
        } else {
            $card->members()->detach([$member->id]);
        }

        $this->recordMcpActivity($card, $request->user(), 'card.members', $this->mcpSource($request), ['user_name' => $member->name]);

        return Response::json(['card_id' => $card->public_id, 'user_id' => $member->public_id, 'assigned' => $assigned]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID).')->required(),
            'user_id' => $schema->string()->description('The board member user public id (ULID), from get-board-meta.')->required(),
            'assigned' => $schema->boolean()->description('true to assign (default), false to unassign.'),
        ];
    }
}
