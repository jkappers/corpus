<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateNotation;
use App\Actions\CreateNotationReply;
use App\Actions\DeleteNotation;
use App\Actions\DeleteNotationReply;
use App\Actions\SetNotationCompleted;
use App\Actions\UpdateNotation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotationReplyRequest;
use App\Http\Requests\StoreNotationRequest;
use App\Http\Requests\UpdateNotationRequest;
use App\Http\Resources\V1\NotationReplyResource;
use App\Http\Resources\V1\NotationResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Notation;
use App\Models\NotationReply;
use App\Models\Page;
use App\Queries\AssignableUsersQuery;
use App\Queries\InboxCountQuery;
use App\Queries\NotationListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Notations', 'Inline notes and tasks anchored to quoted text in a page.', weight: 4)]
class NotationController extends Controller
{
    /**
     * List your inbox.
     *
     * Notations assigned to you, newest first. Open ones by default; pass `status=completed` for done ones.
     */
    public function index(Request $request, NotationListQuery $listNotations): AnonymousResourceCollection
    {
        $status = $request->validate(['status' => ['nullable', 'in:open,completed']])['status'] ?? 'open';

        $notations = $listNotations->inbox($request->user(), $status === 'completed')
            ->cursorPaginate(25)
            ->withQueryString();

        return NotationResource::collection($notations);
    }

    /**
     * Count open inbox items.
     */
    public function count(Request $request, InboxCountQuery $inboxCount): JsonResponse
    {
        return response()->json(['data' => ['open' => $inboxCount->handle($request->user())]]);
    }

    /**
     * List users who can be assigned on a page.
     */
    public function assignableUsers(Page $page, AssignableUsersQuery $assignableUsers): AnonymousResourceCollection
    {
        Gate::authorize('view', $page);

        return UserResource::collection($assignableUsers->handle($page)->get());
    }

    /**
     * List a page's notations.
     */
    public function forPage(Page $page, NotationListQuery $listNotations): AnonymousResourceCollection
    {
        Gate::authorize('view', $page);

        return NotationResource::collection($listNotations->forPage($page)->get());
    }

    /**
     * Add a notation to a page.
     */
    public function store(StoreNotationRequest $request, Page $page, CreateNotation $createNotation): JsonResponse
    {
        $notation = $createNotation->handle($page, $request->user(), $request->validated());

        return (new NotationResource($notation->load(['author:id,name', 'assignee:id,name'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a notation.
     */
    public function update(UpdateNotationRequest $request, Notation $notation, UpdateNotation $updateNotation): NotationResource
    {
        $updateNotation->handle($notation, $request->user(), $request->validated());

        return new NotationResource($notation->load(['author:id,name', 'assignee:id,name']));
    }

    /**
     * Delete a notation.
     */
    public function destroy(Request $request, Notation $notation, DeleteNotation $deleteNotation): Response
    {
        $deleteNotation->handle($notation, $request->user());

        return response()->noContent();
    }

    /**
     * Reply to a notation.
     *
     * Only open notations take replies.
     */
    public function reply(StoreNotationReplyRequest $request, Notation $notation, CreateNotationReply $createReply): JsonResponse
    {
        $reply = $createReply->handle($notation, $request->user(), $request->validated());

        return (new NotationReplyResource($reply->load(['author:id,name', 'notation'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete a reply.
     */
    public function destroyReply(Request $request, NotationReply $reply, DeleteNotationReply $deleteReply): Response
    {
        $deleteReply->handle($reply, $request->user());

        return response()->noContent();
    }

    /**
     * Mark a notation done.
     */
    public function complete(Request $request, Notation $notation, SetNotationCompleted $setCompleted): NotationResource
    {
        return new NotationResource($setCompleted->handle($notation, $request->user())->load('resolvedBy:id,name'));
    }

    /**
     * Reopen a notation.
     */
    public function reopen(Request $request, Notation $notation, SetNotationCompleted $setCompleted): NotationResource
    {
        return new NotationResource($setCompleted->handle($notation, $request->user(), completed: false));
    }
}
