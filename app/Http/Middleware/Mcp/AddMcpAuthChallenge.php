<?php

namespace App\Http\Middleware\Mcp;

use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Exceptions\MissingScopeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global so it runs outside the package's own challenge middleware and has the last word.
 * Completes the WWW-Authenticate challenge from the MCP authorization spec:
 * 401 carries resource_metadata and scope; 403 for a missing scope is `insufficient_scope`.
 */
class AddMcpAuthChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('mcp') || ! $request->isMethod('POST')) {
            return $response;
        }

        $metadata = route('mcp.oauth.protected-resource.nested', ['path' => $request->path()]);
        $scope = Registrar::OAUTH_SCOPE;

        if ($response->getStatusCode() === 401) {
            $response->headers->set('WWW-Authenticate', "Bearer realm=\"mcp\", resource_metadata=\"{$metadata}\", scope=\"{$scope}\"");
        } elseif ($response->getStatusCode() === 403 && ($response->exception ?? null) instanceof MissingScopeException) {
            $response->headers->set('WWW-Authenticate', "Bearer error=\"insufficient_scope\", scope=\"{$scope}\", resource_metadata=\"{$metadata}\"");
        }

        return $response;
    }
}
