<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Label;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Rename a board label and/or change its color.')]
class UpdateLabelTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'label_id' => 'required|string',
            'name' => 'sometimes|nullable|string|max:255',
            'color' => 'sometimes|string|max:9',
        ]);

        $label = Label::with('board')->where('public_id', $request->get('label_id'))->first();

        if ($error = $this->denyUnlessBoardAccess($request, $label?->board)) {
            return $error;
        }

        $update = [];

        if ($request->has('name')) {
            $update['name'] = $request->get('name');
        }
        if ($request->has('color')) {
            $update['color'] = $request->get('color');
        }

        $label->update($update);

        return Response::json(['id' => $label->public_id, 'name' => $label->name, 'color' => $label->color]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'label_id' => $schema->string()->description('The label public id (ULID).')->required(),
            'name' => $schema->string()->description('New name (null to clear).'),
            'color' => $schema->string()->description('New hex color.'),
        ];
    }
}
