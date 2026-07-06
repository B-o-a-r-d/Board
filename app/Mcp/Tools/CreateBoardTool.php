<?php

namespace App\Mcp\Tools;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new board in one of the user workspaces.')]
class CreateBoardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'workspace_id' => 'required|integer',
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $workspace = $user->workspaces()->find($request->get('workspace_id'));

        if (! $workspace) {
            return Response::error('Workspace introuvable ou accès refusé.');
        }

        $name = $request->get('name');

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => BoardVisibility::Private,
            'position' => (int) $workspace->boards()->max('position') + 1,
        ]);

        $board->members()->attach($user->id, ['role' => Role::Owner->value]);

        return Response::json(['id' => $board->id, 'name' => $board->name, 'workspace_id' => $workspace->id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->integer()->description('The workspace id to create the board in.')->required(),
            'name' => $schema->string()->description('The board name.')->required(),
        ];
    }
}
