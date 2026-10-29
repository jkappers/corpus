<?php

use App\Http\Controllers\Mcp\RegisterClientController;
use App\Http\Middleware\Mcp\MarkMcpRequest;
use App\Http\Middleware\Mcp\ValidateMcpOrigin;
use App\Mcp\Servers\CorpusServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

// Discovery documents (RFC 9728, RFC 8414). Registration is ours, to enforce redirect URI rules.
Mcp::oauthRoutes(registration: false);

Route::post('oauth/register', RegisterClientController::class)
    ->middleware('throttle:10,1')
    ->name('mcp.oauth.register');

Mcp::web('/mcp', CorpusServer::class)
    ->middleware([
        ValidateMcpOrigin::class,
        'auth:api',
        CheckToken::using('mcp:use'),
        MarkMcpRequest::class,
    ]);
