<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\BoardList;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a card in a list. Requires the user to be a member of the board.')]
class CreateCardTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate([
            'list_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $user = $request->user();
        $list = BoardList::find($request->get('list_id'));

        if (! $list || ! Gate::forUser($user)->allows('view', $list->board)) {
            return Response::error('Liste introuvable ou accès refusé.');
        }

        $card = $list->cards()->create([
            'board_id' => $list->board_id,
            'created_by' => $user->id,
            'title' => $request->get('title'),
            'description' => $request->get('description'),
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $this->recordMcpActivity($card, $user, 'card.created', $this->mcpSource($request), ['title' => $card->title]);

        return Response::json(['id' => $card->id, 'title' => $card->title, 'list_id' => $list->id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'list_id' => $schema->integer()->description('The list id to create the card in.')->required(),
            'title' => $schema->string()->description('The card title.')->required(),
            'description' => $schema->string()->description('Optional markdown description.'),
        ];
    }
}
