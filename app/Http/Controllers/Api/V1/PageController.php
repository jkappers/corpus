<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreatePage;
use App\Actions\ForceDeletePage;
use App\Actions\RestorePage;
use App\Actions\TrashPage;
use App\Actions\UpdatePage;
use App\Enums\PageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePageRequest;
use App\Http\Requests\StorePageRequest;
use App\Http\Resources\V1\PageResource;
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
 * Markdown pages. Boards are a separate resource (BoardController): a board id is not found here.
 */
#[Group('Pages', 'Markdown pages. A board id is not found here; use Boards.', weight: 2)]
class PageController extends Controller
{
    /**
     * List pages.
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
        $pages = $listPages->handle($request->user(), [
            ...$validated,
            'type' => PageType::Page->value,
            'q' => trim((string) ($validated['q'] ?? '')),
            'trashed' => ($validated['trashed'] ?? null) === 'only',
        ])
            ->paginate(25)
            ->withQueryString();

        return PageResource::collection($pages);
    }

    /**
     * Create a page.
     *
     * Optionally pass an initial `title` and markdown `body`. Without `space_id` it goes in your personal space.
     */
    public function store(StorePageRequest $request, CreatePage $createPage): JsonResponse
    {
        $page = $createPage->handle(
            $request->user(),
            PageType::Page,
            $request->validated('space_id') ? Space::findOrFail($request->validated('space_id')) : null,
            $request->safe()->only(['title', 'body']),
        );

        return (new PageResource($page->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Get a page.
     */
    public function show(Request $request, Page $page): PageResource
    {
        Gate::authorize('view', $page);

        return (new PageResource($page))->additional([
            'meta' => ['can_edit' => $page->space->isEditableBy($request->user())],
        ]);
    }

    /**
     * Update a page.
     *
     * Send the `updated_at` you last read as `base_updated_at` to avoid overwriting someone else's edit:
     * if it changed since, the request fails with 409. The response carries the new `updated_at`.
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePage $updatePage): JsonResponse
    {
        $updatePage->handle(
            $page,
            $request->user(),
            $request->safe()->only(['title', 'body', 'space_id']),
            $request->validated('base_updated_at'),
            $request->validated('mode') === 'append',
        );

        return response()->json(['data' => $page->only(['id', 'updated_at'])]);
    }

    /**
     * Move a page to the trash.
     */
    public function destroy(Request $request, Page $page, TrashPage $trashPage): Response
    {
        $trashPage->handle($page, $request->user());

        return response()->noContent();
    }

    /**
     * Restore a page from the trash.
     */
    public function restore(Request $request, int $id, RestorePage $restorePage): PageResource
    {
        return new PageResource($restorePage->handle($this->trashed($id), $request->user()));
    }

    /**
     * Delete a trashed page permanently.
     */
    public function forceDestroy(Request $request, int $id, ForceDeletePage $forceDeletePage): Response
    {
        $forceDeletePage->handle($this->trashed($id), $request->user());

        return response()->noContent();
    }

    private function trashed(int $id): Page
    {
        return Page::onlyTrashed()->where('type', PageType::Page)->findOrFail($id);
    }
}
