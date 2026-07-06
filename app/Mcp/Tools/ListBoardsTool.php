<?php

namespace App\Mcp\Tools;

use App\Enums\BoardVisibility;
use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List the Kanban boards the authenticated user can access, with their ids and names.')]
class ListBoardsTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $boards = Board::query()
            ->notArchived()
            ->where('is_template', false)
            ->whereHas('workspace.members', fn ($q) => $q->whereKey($user->id))
            ->where(function ($q) use ($user) {
                $q->where('visibility', BoardVisibility::Workspace)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($user->id));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'workspace_id']);

        return Response::json([
            'boards' => $boards->map(fn ($board) => [
                'id' => $board->id,
                'name' => $board->name,
                'workspace_id' => $board->workspace_id,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
