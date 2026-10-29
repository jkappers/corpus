<?php

namespace App\Queries;

use App\Models\Notation;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotationListQuery
{
    /**
     * The user's inbox: notations assigned to them (or written by them), newest first.
     *
     * @return Builder<Notation>
     */
    public function inbox(
        User $user,
        bool $completed = false,
        bool $createdByUser = false,
        ?string $search = null,
        bool $oldestFirst = false,
    ): Builder {
        return Notation::query()
            ->inboxFor($user, $completed, $createdByUser)
            ->when(filled($search), fn ($query) => $query->matching(trim($search)))
            ->with(['page:id,title,type,space_id', 'page.space:id,name', 'author:id,name', 'assignee:id,name'])
            ->orderBy('created_at', $oldestFirst ? 'asc' : 'desc')
            ->orderBy('id', $oldestFirst ? 'asc' : 'desc');
    }

    /**
     * Open and completed totals for the same scope and search.
     *
     * @return array{open: int, completed: int}
     */
    public function inboxCounts(User $user, bool $createdByUser = false, ?string $search = null): array
    {
        return [
            'open' => $this->inbox($user, false, $createdByUser, $search)->count(),
            'completed' => $this->inbox($user, true, $createdByUser, $search)->count(),
        ];
    }

    /**
     * Every notation on a page, oldest first.
     *
     * @return Builder<Notation>
     */
    public function forPage(Page $page): Builder
    {
        return Notation::query()
            ->where('page_id', $page->id)
            ->with(['author:id,name', 'assignee:id,name', 'resolvedBy:id,name', 'replies.author:id,name', 'page.space'])
            ->oldest()
            ->orderBy('id');
    }
}
