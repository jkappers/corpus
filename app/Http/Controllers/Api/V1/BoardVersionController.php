<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RestorePageVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BoardResource;
use App\Http\Resources\V1\PageVersionResource;
use App\Models\Page;
use App\Models\PageVersion;
use App\Queries\PageVersionListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Boards', weight: 3)]
class BoardVersionController extends Controller
{
    /**
     * List board versions.
     */
    public function index(Page $board, PageVersionListQuery $listVersions): AnonymousResourceCollection
    {
        Gate::authorize('view', $board);

        return PageVersionResource::collection(
            $listVersions->handle($board)->cursorPaginate(50)
        );
    }

    /**
     * Get a board version.
     */
    public function show(Page $board, PageVersion $version): PageVersionResource
    {
        Gate::authorize('view', $board);
        abort_unless($version->page_id === $board->id, 404);

        return new PageVersionResource($version->load('user:id,name'));
    }

    /**
     * Restore a board version.
     */
    public function restore(Request $request, Page $board, PageVersion $version, RestorePageVersion $restoreVersion): BoardResource
    {
        return new BoardResource($restoreVersion->handle($board, $version, $request->user())->refresh());
    }
}
