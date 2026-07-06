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

#[Description('Permanently delete a board and all its content. Requires board admin rights. Irreversible.')]
class DeleteBoardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['board_id' => 'required|string']);

        $board = $this->resolvePublicId(Board::class, $request->get('board_id'));

        if (! $board || ! Gate::forUser($request->user())->allows('delete', $board)) {
            return Response::error('Board introuvable ou droits insuffisants (admin requis).');
        }

        $board->delete();

        return Response::json(['id' => $request->get('board_id'), 'deleted' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID) to delete.')->required(),
        ];
    }
}
