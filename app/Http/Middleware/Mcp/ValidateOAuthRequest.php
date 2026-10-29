<?php

namespace App\Http\Middleware\Mcp;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tightens Passport's authorize and token endpoints to the MCP authorization spec.
 *
 * - RFC 8707: a `resource` parameter must name this server's MCP endpoint, otherwise `invalid_target`.
 *   The MCP endpoint is the only resource this authorization server issues tokens for.
 * - PKCE: authorization requests must use S256 (League would otherwise default to `plain`).
 */
class ValidateOAuthRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('oauth/authorize', 'oauth/token')) {
            return $next($request);
        }

        foreach ((array) $request->input('resource', []) as $resource) {
            if (! $this->isMcpResource((string) $resource)) {
                return $this->error('invalid_target', 'The resource parameter must be this server\'s MCP endpoint.');
            }
        }

        if ($request->is('oauth/authorize') && $request->isMethod('GET') && $request->query('code_challenge_method') !== 'S256') {
            return $this->error('invalid_request', 'code_challenge_method must be S256.');
        }

        return $next($request);
    }

    private function isMcpResource(string $resource): bool
    {
        return in_array(rtrim($resource, '/'), [rtrim(url('/mcp'), '/'), rtrim(url('/'), '/')], true);
    }

    private function error(string $error, string $description): Response
    {
        return response()->json(['error' => $error, 'error_description' => $description], 400);
    }
}
