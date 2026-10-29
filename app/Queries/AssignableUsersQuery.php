<?php

namespace App\Queries;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssignableUsersQuery
{
    /**
     * Users a note on this page can be assigned to: everyone for open spaces,
     * admins and members for private ones.
     *
     * @return Builder<User>
     */
    public function handle(Page $page): Builder
    {
        $space = $page->space;

        return User::query()
            ->where(fn (Builder $query) => $space->isPrivate()
                ? $query->where('is_admin', true)
                    ->orWhereHas('spaces', fn ($spaces) => $spaces->whereKey($space->id))
                : $query)
            ->orderBy('name')
            ->select(['id', 'name']);
    }
}
