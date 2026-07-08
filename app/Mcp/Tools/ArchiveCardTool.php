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
        $request->validate(['card_id' => 'required|string']);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        $card->update(['archived_at' => now()]);
        $this->recordMcpActivity($card, $request->user(), 'card.archived', $this->mcpSource($request));

        return Response::json(['id' => $card->public_id, 'archived' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID) to archive.')->required(),
        ];
    }
}
