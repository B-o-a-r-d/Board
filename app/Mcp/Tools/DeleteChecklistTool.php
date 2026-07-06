<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Checklist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a checklist (and all its items) from a card.')]
class DeleteChecklistTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['checklist_id' => 'required|string']);

        $checklist = Checklist::with('card')->where('public_id', $request->get('checklist_id'))->first();

        if ($error = $this->denyUnlessBoardAccess($request, $checklist?->card?->board)) {
            return $error;
        }

        $checklist->delete();

        return Response::json(['id' => $request->get('checklist_id'), 'deleted' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'checklist_id' => $schema->string()->description('The checklist public id (ULID) to delete.')->required(),
        ];
    }
}
