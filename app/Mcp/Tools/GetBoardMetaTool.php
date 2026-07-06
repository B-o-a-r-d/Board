<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List a board reference data: its labels and members, with ids to use when assigning labels or members to cards.')]
class GetBoardMetaTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['board_id' => 'required|string']);

        $board = Board::with(['labels', 'members'])
            ->where('public_id', $request->get('board_id'))
            ->first();

        if ($error = $this->denyUnlessBoardAccess($request, $board)) {
            return $error;
        }

        return Response::json([
            'labels' => $board->labels->map(fn ($l) => ['id' => $l->public_id, 'name' => $l->name, 'color' => $l->color])->all(),
            'members' => $board->members->map(fn ($m) => ['id' => $m->public_id, 'name' => $m->name])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
        ];
    }
}
