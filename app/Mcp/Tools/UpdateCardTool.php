<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update a card: title, description, due date, or completion state. Only provided fields change.')]
class UpdateCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|string',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_at' => 'sometimes|nullable|date',
            'cover_color' => 'sometimes|nullable|string|max:9',
            'completed' => 'sometimes|boolean',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        $update = [];

        foreach (['title', 'description', 'cover_color'] as $field) {
            if ($request->has($field)) {
                $update[$field] = $request->get($field);
            }
        }

        if ($request->has('due_at')) {
            $update['due_at'] = $request->get('due_at') ? Carbon::parse($request->get('due_at')) : null;
        }

        if ($request->has('completed')) {
            $update['completed_at'] = $request->boolean('completed') ? now() : null;
        }

        $card->update($update);

        $this->recordMcpActivity($card, $request->user(), 'card.updated', $this->mcpSource($request));

        return Response::json(['id' => $card->public_id, 'title' => $card->title, 'completed' => $card->completed_at !== null]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID).')->required(),
            'title' => $schema->string()->description('New title.'),
            'description' => $schema->string()->description('New markdown description (null to clear).'),
            'due_at' => $schema->string()->description('Due date ISO-8601 (null to clear).'),
            'cover_color' => $schema->string()->description('Cover hex color (null to clear).'),
            'completed' => $schema->boolean()->description('Mark completed (true) or not (false).'),
        ];
    }
}
