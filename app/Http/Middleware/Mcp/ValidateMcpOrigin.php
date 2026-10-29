<?php

namespace App\Http\Middleware\Mcp;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streamable HTTP requires rejecting an invalid Origin with 403 to block DNS rebinding.
 * Requests without an Origin header (non-browser clients) pass.
 */
class ValidateMcpOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin !== null && ! in_array(rtrim($origin, '/'), $this->allowedOrigins(), true)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Origin not allowed.'],
            ], 403);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function allowedOrigins(): array
    {
        return array_map(
            fn (string $origin) => rtrim($origin, '/'),
            [config('app.url'), ...config('mcp.allowed_origins', [])],
        );
    }
}
