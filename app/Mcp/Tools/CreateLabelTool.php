<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use App\Models\Label;
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
            'board_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'color' => ['required', 'string', Label::COLOR_RULE],
        ]);

        $board = $this->resolvePublicId(Board::class, $request->get('board_id'));

        if ($error = $this->denyUnlessCanContribute($request, $board)) {
            return $error;
        }

        $label = $board->labels()->create([
            'name' => $request->get('name'),
            'color' => $request->get('color'),
        ]);

        return Response::json(['id' => $label->public_id, 'name' => $label->name, 'color' => $label->color]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
            'name' => $schema->string()->description('Optional label name.'),
            'color' => $schema->string()->description('Hex color, e.g. #ef4444.')->required(),
        ];
    }
}
