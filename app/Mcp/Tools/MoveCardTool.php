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

#[Description('Move a card to another list on the same board.')]
class MoveCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|integer',
            'list_id' => 'required|integer',
        ]);

        $user = $request->user();
        $card = Card::find($request->get('card_id'));

        if (! $card || ! Gate::forUser($user)->allows('update', $card)) {
            return Response::error('Carte introuvable ou accès refusé.');
        }

        $list = $card->board->lists()->find($request->get('list_id'));

        if (! $list) {
            return Response::error('Liste de destination introuvable sur ce board.');
        }

        $card->update([
            'board_list_id' => $list->id,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $this->recordMcpActivity($card, $user, 'card.moved', $this->mcpSource($request), ['to_list' => $list->name]);

        return Response::json(['id' => $card->id, 'list_id' => $list->id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id to move.')->required(),
            'list_id' => $schema->integer()->description('The destination list id (same board).')->required(),
        ];
    }
}
