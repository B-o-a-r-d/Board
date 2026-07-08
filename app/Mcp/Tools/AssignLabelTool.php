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
            'card_id' => 'required|string',
            'label_id' => 'required|string',
            'attached' => 'sometimes|boolean',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        $label = $card->board->labels()->where('public_id', $request->get('label_id'))->first();

        if (! $label) {
            return Response::error('Ce label n\'appartient pas au board de la carte.');
        }

        $attached = $request->has('attached') ? $request->boolean('attached') : true;

        if ($attached) {
            $card->labels()->syncWithoutDetaching([$label->id]);
        } else {
            $card->labels()->detach([$label->id]);
        }

        $this->recordMcpActivity($card, $request->user(), 'card.labels', $this->mcpSource($request));

        return Response::json(['card_id' => $card->public_id, 'label_id' => $label->public_id, 'attached' => $attached]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID).')->required(),
            'label_id' => $schema->string()->description('The board label public id (ULID), from get-board-meta.')->required(),
            'attached' => $schema->boolean()->description('true to attach (default), false to detach.'),
        ];
    }
}
