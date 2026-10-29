<?php

namespace App\Http\Controllers;

use App\Http\Resources\V1\BoardResource;
use App\Http\Resources\V1\NotationResource;
use App\Http\Resources\V1\PageResource;
use App\Http\Resources\V1\SearchResultResource;
use App\Models\Page;
use App\Queries\AssignableUsersQuery;
use App\Queries\NotationListQuery;
use App\Queries\PageListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Renders the page screens. Writes go through the REST API (Api\V1\PageController).
 */
class PageController extends Controller
{
    private const RECENT_LIMIT = 50;

    public function __construct(
        private PageListQuery $listPages,
        private NotationListQuery $listNotations,
        private AssignableUsersQuery $assignableUsers,
    ) {}

    /**
     * Home: what changed most recently across every space the user can see.
     */
    public function home(Request $request): InertiaResponse
    {
        $pages = $this->listPages->handle($request->user())->limit(self::RECENT_LIMIT)->get();

        return Inertia::render('pages/index', [
            'pages' => SearchResultResource::collection($pages)->toProps(),
            'space' => null,
            'heading' => 'Recent',
            'type' => null,
            'files' => null,
            'filters' => null,
        ]);
    }

    public function trash(Request $request): InertiaResponse
    {
        $pages = $this->listPages->handle($request->user(), [
            'trashed' => true,
        ])->get();

        return Inertia::render('pages/trash', [
            'pages' => SearchResultResource::collection($pages)->toProps(),
        ]);
    }

    public function show(Request $request, Page $page): InertiaResponse
    {
        Gate::authorize('view', $page);

        return Inertia::render('pages/page', [
            'page' => (new PageResource($page))->toProps(),
            'notations' => NotationResource::collection($this->listNotations->forPage($page)->get())->toProps(),
            'can_edit' => $page->space->isEditableBy($request->user()),
            'default_assignee_id' => $request->user()->id,
            'assignableUsers' => $this->assignableUsers->handle($page)->get(),
        ]);
    }

    public function showBoard(Request $request, Page $board): InertiaResponse
    {
        Gate::authorize('view', $board);

        return Inertia::render('pages/board', [
            'page' => (new BoardResource($board))->toProps(),
            'can_edit' => $board->space->isEditableBy($request->user()),
        ]);
    }
}
