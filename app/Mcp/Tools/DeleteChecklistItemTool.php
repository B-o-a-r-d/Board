<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\ChecklistItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a single checklist item.')]
class DeleteChecklistItemTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['item_id' => 'required|string']);

        $item = ChecklistItem::with('checklist.card')->where('public_id', $request->get('item_id'))->first();

        if ($error = $this->denyUnlessBoardAccess($request, $item?->checklist?->card?->board)) {
            return $error;
        }

        $item->delete();

        return Response::json(['id' => $request->get('item_id'), 'deleted' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->string()->description('The checklist item public id (ULID) to delete.')->required(),
        ];
    }
}
