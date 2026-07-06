<?php

namespace App\Mcp\Tools;

use App\Enums\BoardVisibility;
use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update a board (name, description, background, visibility). Requires board admin rights.')]
class UpdateBoardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'background' => 'sometimes|nullable|string',
            'visibility' => 'sometimes|in:private,workspace',
        ]);

        $board = $this->resolvePublicId(Board::class, $request->get('board_id'));

        if (! $board || ! Gate::forUser($request->user())->allows('update', $board)) {
            return Response::error('Board introuvable ou droits insuffisants (admin requis).');
        }

        $update = [];

        foreach (['name', 'description', 'background'] as $field) {
            if ($request->has($field)) {
                $update[$field] = $request->get($field);
            }
        }
        if ($request->has('visibility')) {
            $update['visibility'] = BoardVisibility::from($request->get('visibility'));
        }

        $board->update($update);

        return Response::json(['id' => $board->public_id, 'name' => $board->name]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
            'name' => $schema->string()->description('New board name.'),
            'description' => $schema->string()->description('New description.'),
            'background' => $schema->string()->description('Background preset key (indigo, ocean, sunset, forest, rose, slate) or null.'),
            'visibility' => $schema->string()->enum(['private', 'workspace'])->description('Board visibility.'),
        ];
    }
}
