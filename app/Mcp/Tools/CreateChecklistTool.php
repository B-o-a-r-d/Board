<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a checklist to a card.')]
class CreateChecklistTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|integer',
            'title' => 'required|string|max:255',
        ]);

        $card = Card::find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $checklist = $card->checklists()->create([
            'title' => $request->get('title'),
            'position' => (int) $card->checklists()->max('position') + 1,
        ]);

        $this->recordMcpActivity($card, $request->user(), 'checklist.created', $this->mcpSource($request));

        return Response::json(['id' => $checklist->id, 'title' => $checklist->title, 'card_id' => $card->id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id.')->required(),
            'title' => $schema->string()->description('The checklist title.')->required(),
        ];
    }
}
