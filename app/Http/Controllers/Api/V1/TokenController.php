<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssuePersonalAccessToken;
use App\Actions\RevokePersonalAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonalAccessTokenRequest;
use App\Http\Resources\V1\TokenResource;
use App\Queries\PersonalAccessTokenQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Laravel\Passport\Token;

/**
 * The signed-in user's personal access tokens. Session authentication only.
 */
#[Group('Account', weight: 7)]
class TokenController extends Controller
{
    /**
     * List your access tokens.
     */
    public function index(Request $request, PersonalAccessTokenQuery $tokens): AnonymousResourceCollection
    {
        return TokenResource::collection($tokens->handle($request->user())->get());
    }

    /**
     * Create an access token.
     *
     * The plain-text token is returned once, in `meta.token`, and cannot be retrieved again.
     */
    public function store(StorePersonalAccessTokenRequest $request, IssuePersonalAccessToken $issueToken): JsonResponse
    {
        $result = $issueToken->handle($request->user(), $request->validated('name'), $request->validated('scopes'));

        return (new TokenResource($result->getToken()))
            ->additional(['meta' => ['token' => $result->accessToken]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Revoke an access token.
     */
    public function destroy(Request $request, string $token, RevokePersonalAccessToken $revokeToken): Response
    {
        $revokeToken->handle($request->user(), Token::query()->findOrFail($token));

        return response()->noContent();
    }
}
