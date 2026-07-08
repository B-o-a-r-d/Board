<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\BoardList;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Rename a list and/or set its header color.')]
class UpdateListTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'list_id' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'cover_color' => 'sometimes|nullable|string|max:9',
        ]);

        $list = $this->resolvePublicId(BoardList::class, $request->get('list_id'));

        if ($error = $this->denyUnlessCanContribute($request, $list?->board)) {
            return $error;
        }

        $update = [];

        if ($request->has('name')) {
            $update['name'] = $request->get('name');
        }
        if ($request->has('cover_color')) {
            $update['cover_color'] = $request->get('cover_color');
        }

        $list->update($update);

        return Response::json(['id' => $list->public_id, 'name' => $list->name, 'cover_color' => $list->cover_color]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'list_id' => $schema->string()->description('The list public id (ULID).')->required(),
            'name' => $schema->string()->description('New list name.'),
            'cover_color' => $schema->string()->description('Header hex color (null to clear).'),
        ];
    }
}
