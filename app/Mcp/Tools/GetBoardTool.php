<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get a board with its lists and cards (ids, titles, completion, due dates).')]
class GetBoardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['board_id' => 'required|integer']);

        $board = Board::find($request->get('board_id'));

        if (! $board || ! Gate::forUser($request->user())->allows('view', $board)) {
            return Response::error('Board introuvable ou accès refusé.');
        }

        $board->load([
            'lists' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
        ]);

        return Response::json([
            'id' => $board->id,
            'name' => $board->name,
            'lists' => $board->lists->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'cards' => $list->cards->map(fn ($card) => [
                    'id' => $card->id,
                    'title' => $card->title,
                    'completed' => $card->completed_at !== null,
                    'due_at' => optional($card->due_at)->toIso8601String(),
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
            'board_id' => $schema->integer()->description('The board id.')->required(),
        ];
    }
}
