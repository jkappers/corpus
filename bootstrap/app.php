<?php

use App\Exceptions\StaleWriteException;
use App\Http\Middleware\Api\AuthenticateApiRequest;
use App\Http\Middleware\Api\RequireScope;
use App\Http\Middleware\Api\RequireSessionAuth;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Mcp\AddMcpAuthChallenge;
use App\Http\Middleware\Mcp\ValidateOAuthRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // The REST API serves the first-party SPA (session + CSRF) and partners (bearer tokens, no session).
        $middleware->api(prepend: [AuthenticateApiRequest::class]);
        $middleware->alias(['api.scope' => RequireScope::class, 'api.session' => RequireSessionAuth::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequireScope::class);

        $middleware->append([ValidateOAuthRequest::class, AddMcpAuthChallenge::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (StaleWriteException $exception) => response()->json([
            'message' => 'The page changed since base_updated_at.',
            'current_updated_at' => $exception->currentUpdatedAt,
        ], 409));

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
