<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PageType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SearchResultResource;
use App\Queries\PageListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Search pages and boards together. Each result carries its `type` and a `links.self` URL to the
 * matching resource (/pages or /boards). Clients should skip types they do not know; more may be added.
 */
#[Group('Search', 'Find pages and boards in one list.', weight: 1)]
class SearchController extends Controller
{
    /**
     * Search pages and boards.
     *
     * Matches titles. Each result has a `type` (`page` or `board`) and `links.self`, the URL of its full record.
     * Skip types you don't recognise: more may be added.
     */
    public function __invoke(Request $request, PageListQuery $listPages): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::enum(PageType::class)],
            'space_id' => ['nullable', 'integer'],
            'trashed' => ['nullable', 'in:only'],
            'sort' => ['nullable', 'in:title,space,updated'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $results = $listPages->handle($request->user(), [
            ...$validated,
            'q' => trim((string) ($validated['q'] ?? '')),
            'trashed' => ($validated['trashed'] ?? null) === 'only',
        ])
            ->paginate(25)
            ->withQueryString();

        return SearchResultResource::collection($results);
    }
}
