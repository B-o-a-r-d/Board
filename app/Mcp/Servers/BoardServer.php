<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddChecklistItemTool;
use App\Mcp\Tools\AddCommentTool;
use App\Mcp\Tools\ArchiveCardTool;
use App\Mcp\Tools\ArchiveListTool;
use App\Mcp\Tools\AssignLabelTool;
use App\Mcp\Tools\AssignMemberTool;
use App\Mcp\Tools\AttachFileFromUrlTool;
use App\Mcp\Tools\AttachFileTool;
use App\Mcp\Tools\CreateBoardTool;
use App\Mcp\Tools\CreateCardTool;
use App\Mcp\Tools\CreateChecklistTool;
use App\Mcp\Tools\CreateLabelTool;
use App\Mcp\Tools\CreateListTool;
use App\Mcp\Tools\DeleteBoardTool;
use App\Mcp\Tools\DeleteChecklistItemTool;
use App\Mcp\Tools\DeleteChecklistTool;
use App\Mcp\Tools\DeleteLabelTool;
use App\Mcp\Tools\DuplicateCardTool;
use App\Mcp\Tools\GetBoardMetaTool;
use App\Mcp\Tools\GetBoardTool;
use App\Mcp\Tools\GetCardTool;
use App\Mcp\Tools\ListAutomationsTool;
use App\Mcp\Tools\ListBoardsTool;
use App\Mcp\Tools\MoveCardTool;
use App\Mcp\Tools\RunAutomationTool;
use App\Mcp\Tools\ToggleChecklistItemTool;
use App\Mcp\Tools\UpdateBoardTool;
use App\Mcp\Tools\UpdateCardTool;
use App\Mcp\Tools\UpdateLabelTool;
use App\Mcp\Tools\UpdateListTool;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\PluginRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Board')]
#[Version('1.2.0')]
#[Instructions('Full control of the Board Kanban app on behalf of the authenticated user. Boards, lists, cards, checklists, labels, member assignments, comments, attachments (by URL), and manual automations. Use get-board to explore, get-board-meta for label/member ids, and get-card for full card detail (including checklist item ids). All actions respect the user membership/role, are logged as "mcp:" activities, and broadcast live like web UI actions.')]
class BoardServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Boards
        ListBoardsTool::class,
        GetBoardTool::class,
        GetBoardMetaTool::class,
        CreateBoardTool::class,
        UpdateBoardTool::class,
        DeleteBoardTool::class,
        // Lists
        CreateListTool::class,
        UpdateListTool::class,
        ArchiveListTool::class,
        // Cards
        GetCardTool::class,
        CreateCardTool::class,
        UpdateCardTool::class,
        MoveCardTool::class,
        DuplicateCardTool::class,
        ArchiveCardTool::class,
        AddCommentTool::class,
        AttachFileTool::class,
        AttachFileFromUrlTool::class,
        // Checklists
        CreateChecklistTool::class,
        AddChecklistItemTool::class,
        ToggleChecklistItemTool::class,
        DeleteChecklistItemTool::class,
        DeleteChecklistTool::class,
        // Labels
        CreateLabelTool::class,
        UpdateLabelTool::class,
        DeleteLabelTool::class,
        AssignLabelTool::class,
        // Members
        AssignMemberTool::class,
        // Automations
        ListAutomationsTool::class,
        RunAutomationTool::class,
    ];

    /**
     * Merge MCP tools contributed by installed plugins (Power-Ups) — a plugin
     * that implements ProvidesMcpTools exposes its tools through this server
     * with no core changes.
     */
    protected function boot(): void
    {
        parent::boot();

        foreach (app(PluginRegistry::class)->all() as $plugin) {
            if ($plugin instanceof ProvidesMcpTools) {
                $this->tools = array_merge($this->tools, $plugin->mcpTools());
            }
        }
    }

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
