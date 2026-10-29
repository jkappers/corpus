<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSpace;
use App\Actions\DeleteSpace;
use App\Actions\DeleteSpaceContent;
use App\Actions\TransferSpaceContent;
use App\Actions\UpdateSpace;
use App\Enums\SpaceVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteSpaceContentRequest;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\TransferSpaceContentRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Http\Resources\V1\SpaceResource;
use App\Models\Space;
use App\Queries\SpaceListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Spaces', 'Spaces hold pages and boards and control who can see and edit them.', weight: 6)]
class SpaceController extends Controller
{
    /**
     * List spaces.
     */
    public function index(Request $request, SpaceListQuery $listSpaces): AnonymousResourceCollection
    {
        $spaces = $listSpaces->handle($request->user())->get();

        return SpaceResource::collection($spaces)->additional([
            'meta' => ['can_create' => $request->user()->can('create', Space::class)],
        ]);
    }

    /**
     * Get a space.
     */
    public function show(Space $space): SpaceResource
    {
        Gate::authorize('view', $space);

        $space->loadCount(['users', 'pages']);

        if (request()->user()->can('update', $space)) {
            $space->load('users:id,name,email');
        }

        return new SpaceResource($space);
    }

    /**
     * Create a space.
     */
    public function store(StoreSpaceRequest $request, CreateSpace $createSpace): JsonResponse
    {
        $space = $createSpace->handle(
            $request->user(),
            $request->validated('name'),
            SpaceVisibility::from($request->validated('visibility', SpaceVisibility::Open->value)),
        );

        return (new SpaceResource($space))->response()->setStatusCode(201);
    }

    /**
     * Update a space.
     */
    public function update(UpdateSpaceRequest $request, Space $space, UpdateSpace $updateSpace): SpaceResource
    {
        return new SpaceResource($updateSpace->handle($space, $request->user(), $request->validated()));
    }

    /**
     * Delete a space.
     */
    public function destroy(Request $request, Space $space, DeleteSpace $deleteSpace): Response
    {
        $deleteSpace->handle($space, $request->user());

        return response()->noContent();
    }

    /**
     * Delete all content in a space.
     *
     * Permanently deletes every page and board, trashed ones included, with their attachments.
     * Requires `confirmation` to be exactly "I accept the consequences".
     */
    public function destroyContent(DeleteSpaceContentRequest $request, Space $space, DeleteSpaceContent $deleteContent): Response
    {
        $deleteContent->handle($space, $request->user());

        return response()->noContent();
    }

    /**
     * Transfer all content to another space.
     *
     * Moves every page and board, trashed ones included, to a space the user can edit.
     */
    public function transferContent(TransferSpaceContentRequest $request, Space $space, TransferSpaceContent $transferContent): JsonResponse
    {
        $moved = $transferContent->handle($space, $request->user(), (int) $request->validated('target_space_id'));

        return response()->json(['moved' => $moved]);
    }
}
