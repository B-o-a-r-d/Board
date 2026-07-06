<?php

use App\Mcp\Servers\BoardServer;
use Laravel\Mcp\Facades\Mcp;

// Board MCP server — authenticated with the same Sanctum personal access tokens
// used by the REST API. The per-tool master switch (Setting 'mcp_enabled') and
// per-user board authorization are enforced inside the server's tools.
Mcp::web('/mcp/board', BoardServer::class)
    ->middleware('auth:sanctum');
