<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Archive a card (move it to the board trash).')]
class ArchiveCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['card_id' => 'required|integer']);

        $card = Card::find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $card->update(['archived_at' => now()]);
        $this->recordMcpActivity($card, $request->user(), 'card.archived', $this->mcpSource($request));

        return Response::json(['id' => $card->id, 'archived' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id to archive.')->required(),
        ];
    }
}
