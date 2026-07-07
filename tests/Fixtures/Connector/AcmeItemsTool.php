<?php

namespace Tests\Fixtures\Connector;

use Board\PluginSdk\Contracts\PluginContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Lists items of an Acme resource using the connected instance's stored token.
 * Decoupled from the host through the SDK's PluginContext, exactly like a real
 * plugin MCP tool — it lets the plugin-system test exercise MCP discovery.
 */
#[Description('List items of an Acme resource connected to a board through the Acme Power-Up.')]
class AcmeItemsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'board_id' => 'required|string',
            'resource' => 'required|string',
        ]);

        $context = app(PluginContext::class);
        $boardId = (string) $request->get('board_id');

        if (! $context->userCanAccessBoard($boardId)) {
            return Response::error('Board not found or access denied.');
        }

        $config = $context->boardPluginConfig($boardId, 'acme');

        if ($config === null) {
            return Response::error('The Acme Power-Up is not installed/active on this board.');
        }

        $items = Http::withToken((string) ($config['token'] ?? ''))->acceptJson()
            ->get(AcmePlugin::API.'/items', ['resource' => (string) $request->get('resource')])
            ->json();

        return Response::json([
            'items' => collect(is_array($items) ? $items : [])->map(fn (array $item): array => [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? null,
                'author' => $item['author'] ?? null,
                'url' => $item['url'] ?? null,
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
            'resource' => $schema->string()->description('The Acme resource, as team/project.')->required(),
        ];
    }
}
