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
            'board_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $board = $this->resolvePublicId(Board::class, $request->get('board_id'));

        if ($error = $this->denyUnlessCanContribute($request, $board)) {
            return $error;
        }

        $list = $board->lists()->create([
            'name' => $request->get('name'),
            'position' => (int) $board->lists()->max('position') + 1,
        ]);

        return Response::json(['id' => $list->public_id, 'name' => $list->name, 'board_id' => $board->public_id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
            'name' => $schema->string()->description('The list name.')->required(),
        ];
    }
}
