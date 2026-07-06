<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a label on a board.')]
class CreateLabelTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|integer',
            'name' => 'nullable|string|max:255',
            'color' => 'required|string|max:9',
        ]);

        $board = Board::find($request->get('board_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $board)) {
            return $error;
        }

        $label = $board->labels()->create([
            'name' => $request->get('name'),
            'color' => $request->get('color'),
        ]);

        return Response::json(['id' => $label->id, 'name' => $label->name, 'color' => $label->color]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->integer()->description('The board id.')->required(),
            'name' => $schema->string()->description('Optional label name.'),
            'color' => $schema->string()->description('Hex color, e.g. #ef4444.')->required(),
        ];
    }
}
