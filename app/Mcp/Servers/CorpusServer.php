<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddNotationTool;
use App\Mcp\Tools\CompleteNotationTool;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\ListNotationsTool;
use App\Mcp\Tools\ListSpacesTool;
use App\Mcp\Tools\ListVersionsTool;
use App\Mcp\Tools\RestoreVersionTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\TrashTool;
use App\Mcp\Tools\UpdatePageTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * One OAuth scope (`mcp:use`) grants the whole toolset, acting as the user. The REST scopes do not apply here:
 * the tool list is the boundary, so it must never include admin tools. Tools call the same Actions and Queries
 * as the REST API, and those Actions enforce authorization and domain rules.
 */
#[Name('Corpus')]
#[Version('1.0.0')]
#[Instructions('Corpus holds markdown pages and boards (canvases of nodes and edges) grouped in spaces. Use search to find either, and fetch to read one by id; each result says its type. search, fetch, trash, list_versions and restore_version work on both. Content tools are for pages only: create_page, update_page and notations; boards are read-only here. Fetch a page before editing it and pass its updated_at as base_updated_at. Notations are inline notes and tasks anchored to quoted page text. You act as the signed-in user and see only what they can see.')]
class CorpusServer extends Server
{
    protected array $tools = [
        SearchTool::class,
        FetchTool::class,
        CreatePageTool::class,
        UpdatePageTool::class,
        TrashTool::class,
        ListSpacesTool::class,
        ListNotationsTool::class,
        AddNotationTool::class,
        CompleteNotationTool::class,
        ListVersionsTool::class,
        RestoreVersionTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
