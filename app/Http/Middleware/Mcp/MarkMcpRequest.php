<?php

namespace App\Http\Middleware\Mcp;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags audit log entries written while serving an MCP request.
 */
class MarkMcpRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('audit.via', 'mcp');

        return $next($request);
    }
}
