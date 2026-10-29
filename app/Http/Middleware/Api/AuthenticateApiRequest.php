<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks how an API request authenticates, exclusively.
 *
 * - With a bearer token, only the `api` (Passport) guard is consulted. No session is started,
 *   so a stray cookie can never authenticate the request, and CSRF does not apply.
 * - Without one, the first-party SPA path applies: the `web` session guard with CSRF protection.
 */
class AuthenticateApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            Auth::shouldUse('api');

            return $next($request);
        }

        Auth::shouldUse('web');

        return (new Pipeline(app()))
            ->send($request)
            ->through([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                PreventRequestForgery::class,
            ])
            ->then($next);
    }
}
