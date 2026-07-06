<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Label;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a board label (also removes it from every card).')]
class DeleteLabelTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['label_id' => 'required|string']);

        $label = Label::with('board')->where('public_id', $request->get('label_id'))->first();

        if ($error = $this->denyUnlessBoardAccess($request, $label?->board)) {
            return $error;
        }

        $label->delete();

        return Response::json(['id' => $request->get('label_id'), 'deleted' => true]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'label_id' => $schema->string()->description('The label public id (ULID) to delete.')->required(),
        ];
    }
}
