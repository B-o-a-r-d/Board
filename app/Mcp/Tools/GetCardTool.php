<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get a single card in full detail: description, due date, completion, labels, assigned members, and checklists with their items and completion state.')]
class GetCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['card_id' => 'required|integer']);

        $card = Card::with(['labels', 'members', 'checklists.items'])->find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        return Response::json([
            'id' => $card->id,
            'board_id' => $card->board_id,
            'list_id' => $card->board_list_id,
            'title' => $card->title,
            'description' => $card->description,
            'completed' => $card->completed_at !== null,
            'due_at' => optional($card->due_at)->toIso8601String(),
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->all(),
            'members' => $card->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->id,
                'title' => $cl->title,
                'items' => $cl->items->map(fn ($item) => [
                    'id' => $item->id,
                    'content' => $item->content,
                    'completed' => (bool) $item->is_completed,
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id.')->required(),
        ];
    }
}
