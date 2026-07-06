<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\BoardList;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Archive a list and its cards (move them to the board trash).')]
class ArchiveListTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['list_id' => 'required|integer']);

        $list = BoardList::find($request->get('list_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $list?->board)) {
            return $error;
        }

        $list->update(['archived_at' => now()]);

        return Response::json(['id' => $list->id, 'archived' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'list_id' => $schema->integer()->description('The list id to archive.')->required(),
        ];
    }
}
