<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreatePage;
use App\Actions\ForceDeletePage;
use App\Actions\RestorePage;
use App\Actions\TrashPage;
use App\Actions\UpdatePage;
use App\Enums\PageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateBoardRequest;
use App\Http\Requests\StoreBoardRequest;
use App\Http\Resources\V1\BoardResource;
use App\Models\Page;
use App\Models\Space;
use App\Queries\PageListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Boards (canvas content, sent and returned as `board`). Pages are a separate resource: a page id is not found here.
 */
#[Group('Boards', 'Canvases of nodes and edges, sent and returned as `board`. A page id is not found here; use Pages.', weight: 3)]
class BoardController extends Controller
{
    /**
     * List boards.
     */
    public function index(Request $request, PageListQuery $listPages): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'trashed' => ['nullable', 'in:only'],
            'sort' => ['nullable', 'in:title,space,updated'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $boards = $listPages->handle($request->user(), [
            ...$validated,
            'type' => PageType::Board->value,
            'q' => trim((string) ($validated['q'] ?? '')),
            'trashed' => ($validated['trashed'] ?? null) === 'only',
        ])
            ->paginate(25)
            ->withQueryString();

        return BoardResource::collection($boards);
    }

    /**
     * Create a board.
     *
     * Optionally pass an initial `title` and `board` content. Without `space_id` it goes in your personal space.
     */
    public function store(StoreBoardRequest $request, CreatePage $createPage): JsonResponse
    {
        $board = $createPage->handle(
            $request->user(),
            PageType::Board,
            $request->validated('space_id') ? Space::findOrFail($request->validated('space_id')) : null,
            $request->safe()->only(['title', 'data']),
        );

        return (new BoardResource($board->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Get a board.
     */
    public function show(Request $request, Page $board): BoardResource
    {
        Gate::authorize('view', $board);

        return (new BoardResource($board))->additional([
            'meta' => ['can_edit' => $board->space->isEditableBy($request->user())],
        ]);
    }

    /**
     * Update a board.
     *
     * Send the `updated_at` you last read as `base_updated_at` to avoid overwriting someone else's edit:
     * if it changed since, the request fails with 409. The response carries the new `updated_at`.
     */
    public function update(UpdateBoardRequest $request, Page $board, UpdatePage $updatePage): JsonResponse
    {
        $updatePage->handle(
            $board,
            $request->user(),
            $request->safe()->only(['title', 'data', 'space_id']),
            $request->validated('base_updated_at'),
        );

        return response()->json(['data' => $board->only(['id', 'updated_at'])]);
    }

    /**
     * Move a board to the trash.
     */
    public function destroy(Request $request, Page $board, TrashPage $trashPage): Response
    {
        $trashPage->handle($board, $request->user());

        return response()->noContent();
    }

    /**
     * Restore a board from the trash.
     */
    public function restore(Request $request, int $id, RestorePage $restorePage): BoardResource
    {
        return new BoardResource($restorePage->handle($this->trashed($id), $request->user()));
    }

    /**
     * Delete a trashed board permanently.
     */
    public function forceDestroy(Request $request, int $id, ForceDeletePage $forceDeletePage): Response
    {
        $forceDeletePage->handle($this->trashed($id), $request->user());

        return response()->noContent();
    }

    private function trashed(int $id): Page
    {
        return Page::onlyTrashed()->where('type', PageType::Board)->findOrFail($id);
    }
}
