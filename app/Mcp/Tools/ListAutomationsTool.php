<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Board;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List a board automations (id, name, trigger, action, active state, and whether it is a manual button that can be run on a card).')]
class ListAutomationsTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request): Response
    {
        $request->validate(['board_id' => 'required|string']);

        $board = $this->resolvePublicId(Board::class, $request->get('board_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $board)) {
            return $error;
        }

        return Response::json([
            'automations' => $board->automations->map(fn ($a) => [
                'id' => $a->public_id,
                'name' => $a->name,
                'trigger' => $a->trigger_type,
                'action' => $a->action_type,
                'active' => $a->is_active,
                'manual' => $a->trigger_type === 'manual',
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'board_id' => $schema->string()->description('The board public id (ULID).')->required(),
        ];
    }
}
