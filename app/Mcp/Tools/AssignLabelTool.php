<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach or detach a board label on a card. Set "attached" to false to remove it.')]
class AssignLabelTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|integer',
            'label_id' => 'required|integer',
            'attached' => 'sometimes|boolean',
        ]);

        $card = Card::find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $labelId = (int) $request->get('label_id');

        if (! $card->board->labels()->whereKey($labelId)->exists()) {
            return Response::error('Ce label n\'appartient pas au board de la carte.');
        }

        $attached = $request->has('attached') ? $request->boolean('attached') : true;

        if ($attached) {
            $card->labels()->syncWithoutDetaching([$labelId]);
        } else {
            $card->labels()->detach([$labelId]);
        }

        $this->recordMcpActivity($card, $request->user(), 'card.labels', $this->mcpSource($request));

        return Response::json(['card_id' => $card->id, 'label_id' => $labelId, 'attached' => $attached]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->integer()->description('The card id.')->required(),
            'label_id' => $schema->integer()->description('The board label id.')->required(),
            'attached' => $schema->boolean()->description('true to attach (default), false to detach.'),
        ];
    }
}
