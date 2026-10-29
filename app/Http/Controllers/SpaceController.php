<?php

namespace App\Http\Controllers;

use App\Enums\PageType;
use App\Http\Resources\V1\AttachmentResource;
use App\Http\Resources\V1\SearchResultResource;
use App\Http\Resources\V1\SpaceResource;
use App\Models\Page;
use App\Models\Space;
use App\Queries\AttachmentListQuery;
use App\Queries\PageListQuery;
use App\Queries\SpaceListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Renders the space screens. Writes go through the REST API (Api\V1\SpaceController).
 */
class SpaceController extends Controller
{
    private const FILES = 'file';

    public function __construct(
        private SpaceListQuery $listSpaces,
        private PageListQuery $listPages,
        private AttachmentListQuery $listAttachments,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $spaces = $this->listSpaces->handle($request->user())->whereNull('owner_id')->get();

        return Inertia::render('spaces/index', [
            'space_list' => SpaceResource::collection($spaces)->toProps(),
            'can_create' => $request->user()->can('create', Space::class),
        ]);
    }

    public function create(): InertiaResponse
    {
        Gate::authorize('create', Space::class);

        return Inertia::render('spaces/create');
    }

    public function edit(Space $space): InertiaResponse
    {
        Gate::authorize('update', $space);

        return Inertia::render('spaces/settings/general', [
            'space' => (new SpaceResource($space))->toProps(),
        ]);
    }

    public function members(Space $space): InertiaResponse
    {
        Gate::authorize('manageMembers', $space);

        $space->load('users:id,name,email');

        return Inertia::render('spaces/settings/members', [
            'space' => (new SpaceResource($space))->toProps(),
        ]);
    }

    public function danger(Request $request, Space $space): InertiaResponse
    {
        Gate::authorize('update', $space);

        $space->loadCount(['pages' => fn ($pages) => $pages->withTrashed()]);

        $transferTargets = Space::query()
            ->editableBy($request->user())
            ->whereKeyNot($space->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('spaces/settings/danger', [
            'space' => (new SpaceResource($space))->toProps(),
            'transfer_targets' => $transferTargets,
        ]);
    }

    /**
     * A space's contents, optionally narrowed to one kind: `?type=page`, `board` or `file`.
     */
    public function show(Request $request, Space $space): InertiaResponse
    {
        Gate::authorize('view', $space);

        $type = $request->query('type');
        $pageType = PageType::tryFrom((string) $type);
        $showsFiles = $type === self::FILES;

        $pages = $showsFiles ? collect() : $this->listPages->handle($request->user(), [
            'space_id' => $space->id,
            'type' => $pageType?->value,
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ])->get();

        $files = $showsFiles
            ? AttachmentResource::collection(
                $this->listAttachments->handle($request->user(), spaceId: $space->id)->limit(200)->get()
            )->toProps()
            : null;

        return Inertia::render('pages/index', [
            'pages' => SearchResultResource::collection($pages)->toProps(),
            'space' => (new SpaceResource($space))->toProps(),
            'heading' => $space->name,
            'type' => $showsFiles ? self::FILES : $pageType?->value,
            'files' => $files,
            'filters' => Page::resolveSort($request->query('sort'), $request->query('direction')),
        ]);
    }
}
