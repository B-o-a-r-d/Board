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
        $request->validate(['card_id' => 'required|string']);

        $card = Card::with(['board', 'list', 'labels', 'members', 'checklists.items'])
            ->where('public_id', $request->get('card_id'))
            ->first();

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        return Response::json([
            'id' => $card->public_id,
            'board_id' => $card->board?->public_id,
            'list_id' => $card->list?->public_id,
            'title' => $card->title,
            'description' => $card->description,
            'completed' => $card->completed_at !== null,
            'due_at' => optional($card->due_at)->toIso8601String(),
            'labels' => $card->labels->map(fn ($l) => ['id' => $l->public_id, 'name' => $l->name, 'color' => $l->color])->all(),
            'members' => $card->members->map(fn ($m) => ['id' => $m->public_id, 'name' => $m->name])->all(),
            'checklists' => $card->checklists->map(fn ($cl) => [
                'id' => $cl->public_id,
                'title' => $cl->title,
                'items' => $cl->items->map(fn ($item) => [
                    'id' => $item->public_id,
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
            'card_id' => $schema->string()->description('The card public id (ULID).')->required(),
        ];
    }
}
