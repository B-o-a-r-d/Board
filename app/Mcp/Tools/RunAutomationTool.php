<?php

namespace App\Mcp\Tools;

use App\Automations\AutomationEngine;
use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Run a manual automation (a card button) against a card. Use list-automations to find manual automation ids.')]
class RunAutomationTool extends Tool
{
    use InteractsWithMcpBoard;

    public function handle(Request $request, AutomationEngine $engine): Response
    {
        $request->validate([
            'automation_id' => 'required|string',
            'card_id' => 'required|string',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        $automation = $card->board->automations()
            ->where('trigger_type', 'manual')
            ->where('is_active', true)
            ->where('public_id', $request->get('automation_id'))
            ->first();

        if (! $automation) {
            return Response::error('Automation manuelle introuvable ou inactive sur ce board.');
        }

        $ran = $engine->runManual($automation, $card);

        $this->recordMcpActivity($card, $request->user(), 'automation.run', $this->mcpSource($request), ['automation' => $automation->name]);

        return Response::json(['automation_id' => $automation->public_id, 'card_id' => $card->public_id, 'ran' => $ran]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'automation_id' => $schema->string()->description('The manual automation public id (ULID), from list-automations.')->required(),
            'card_id' => $schema->string()->description('The card public id (ULID) to run it against.')->required(),
        ];
    }
}
