<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\ChecklistItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Check or uncheck a checklist item. Omit "completed" to toggle it.')]
class ToggleChecklistItemTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'item_id' => 'required|integer',
            'completed' => 'sometimes|boolean',
        ]);

        $item = ChecklistItem::with('checklist.card')->find($request->get('item_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $item?->checklist?->card?->board)) {
            return $error;
        }

        $completed = $request->has('completed')
            ? $request->boolean('completed')
            : ! $item->is_completed;

        $item->update(['is_completed' => $completed]);

        $this->recordMcpActivity($item->checklist->card, $request->user(), 'checklist.item.toggled', $this->mcpSource($request));

        return Response::json(['id' => $item->id, 'completed' => (bool) $item->is_completed]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('The checklist item id.')->required(),
            'completed' => $schema->boolean()->description('Set completion explicitly (omit to toggle).'),
        ];
    }
}
