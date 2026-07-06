<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Checklist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add an item to a checklist.')]
class AddChecklistItemTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'checklist_id' => 'required|string',
            'content' => 'required|string|max:1000',
        ]);

        $checklist = Checklist::with('card')->where('public_id', $request->get('checklist_id'))->first();

        if ($error = $this->denyUnlessBoardAccess($request, $checklist?->card?->board)) {
            return $error;
        }

        $item = $checklist->items()->create([
            'content' => $request->get('content'),
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        $this->recordMcpActivity($checklist->card, $request->user(), 'checklist.item.added', $this->mcpSource($request));

        return Response::json(['id' => $item->public_id, 'content' => $item->content, 'checklist_id' => $checklist->public_id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->string()->description('The checklist public id (ULID).')->required(),
            'content' => $schema->string()->description('The item text.')->required(),
        ];
    }
}
