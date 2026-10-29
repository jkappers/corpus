<?php

namespace App\Queries;

use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Database\Eloquent\Builder;

class PageVersionListQuery
{
    /**
     * Saved versions of a page without content, newest first.
     *
     * @return Builder<PageVersion>
     */
    public function handle(Page $page): Builder
    {
        return PageVersion::query()
            ->where('page_id', $page->id)
            ->with('user:id,name')
            ->select(['id', 'user_id', 'title', 'reason', 'created_at'])
            ->latest('id');
    }
}
