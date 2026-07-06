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
            'automation_id' => 'required|integer',
            'card_id' => 'required|integer',
        ]);

        $card = Card::find($request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $automation = $card->board->automations()
            ->where('trigger_type', 'manual')
            ->where('is_active', true)
            ->find($request->get('automation_id'));

        if (! $automation) {
            return Response::error('Automation manuelle introuvable ou inactive sur ce board.');
        }

        $ran = $engine->runManual($automation, $card);

        $this->recordMcpActivity($card, $request->user(), 'automation.run', $this->mcpSource($request), ['automation' => $automation->name]);

        return Response::json(['automation_id' => $automation->id, 'card_id' => $card->id, 'ran' => $ran]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'automation_id' => $schema->integer()->description('The manual automation id.')->required(),
            'card_id' => $schema->integer()->description('The card to run it against.')->required(),
        ];
    }
}
