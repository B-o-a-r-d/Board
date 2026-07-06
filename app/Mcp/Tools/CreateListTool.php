<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new list (column) on a board.')]
class CreateListTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        $board = Board::find($request->get('board_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $board)) {
            return $error;
        }

        $list = $board->lists()->create([
            'name' => $request->get('name'),
            'position' => (int) $board->lists()->max('position') + 1,
        ]);

        return Response::json(['id' => $list->id, 'name' => $list->name, 'board_id' => $board->id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->integer()->description('The board id.')->required(),
            'name' => $schema->string()->description('The list name.')->required(),
        ];
    }
}
