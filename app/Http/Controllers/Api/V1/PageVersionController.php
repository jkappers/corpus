<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RestorePageVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PageResource;
use App\Http\Resources\V1\PageVersionResource;
use App\Models\Page;
use App\Models\PageVersion;
use App\Queries\PageVersionListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Pages', weight: 2)]
class PageVersionController extends Controller
{
    /**
     * List page versions.
     */
    public function index(Page $page, PageVersionListQuery $listVersions): AnonymousResourceCollection
    {
        Gate::authorize('view', $page);

        return PageVersionResource::collection(
            $listVersions->handle($page)->cursorPaginate(50)
        );
    }

    /**
     * Get a page version.
     */
    public function show(Page $page, PageVersion $version): PageVersionResource
    {
        Gate::authorize('view', $page);
        abort_unless($version->page_id === $page->id, 404);

        return new PageVersionResource($version->load('user:id,name'));
    }

    /**
     * Restore a page version.
     */
    public function restore(Request $request, Page $page, PageVersion $version, RestorePageVersion $restoreVersion): PageResource
    {
        return new PageResource($restoreVersion->handle($page, $version, $request->user())->refresh());
    }
}
