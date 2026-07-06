<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Duplicate a card within its list, copying its description, cover, labels and members.')]
class DuplicateCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['card_id' => 'required|integer']);

        $card = Card::with(['labels', 'members'])->find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $copy = $card->list->cards()->create([
            'board_id' => $card->board_id,
            'created_by' => $request->user()->id,
            'title' => $card->title.' (copie)',
            'description' => $card->description,
            'cover_path' => $card->cover_path,
            'cover_color' => $card->cover_color,
            'due_at' => $card->due_at,
            'position' => (int) $card->list->cards()->max('position') + 1,
        ]);

        $copy->labels()->attach($card->labels->pluck('id'));
        $copy->members()->attach($card->members->pluck('id'));

        $this->recordMcpActivity($copy, $request->user(), 'card.duplicated', $this->mcpSource($request), ['from' => $card->id]);

        return Response::json(['id' => $copy->id, 'title' => $copy->title]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id to duplicate.')->required(),
        ];
    }
}
