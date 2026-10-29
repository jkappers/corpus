<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account and credential management is for signed-in users only, so a token can never change the profile or password, or create or revoke tokens.
 */
class RequireSessionAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->token() !== null) {
            return response()->json(['message' => 'Sign in to the web app to do this; access tokens cannot.'], 403);
        }

        return $next($request);
    }
}
