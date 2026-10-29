<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TokenResource;
use App\Queries\PersonalAccessTokenQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Renders the API token screen. Tokens are created and revoked through the REST API.
 */
class TokenController extends Controller
{
    public function edit(Request $request, PersonalAccessTokenQuery $tokens): InertiaResponse
    {
        return Inertia::render('settings/tokens', [
            'tokens' => TokenResource::collection($tokens->handle($request->user())->get())->toProps(),
        ]);
    }
}
