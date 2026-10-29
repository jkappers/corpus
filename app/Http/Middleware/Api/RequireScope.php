<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token requests must hold the named scope. Session requests (the first-party SPA) are not scoped.
 */
class RequireScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->user()?->token();

        if ($token !== null && ! $token->can($scope)) {
            return response()->json([
                'message' => 'The access token is missing the required scope.',
                'required_scope' => $scope,
            ], 403, [
                'WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="'.$scope.'"',
            ]);
        }

        return $next($request);
    }
}
