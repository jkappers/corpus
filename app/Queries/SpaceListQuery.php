<?php

namespace App\Queries;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SpaceListQuery
{
    /**
     * Spaces the user can see, with member and page counts.
     *
     * @return Builder<Space>
     */
    public function handle(User $user): Builder
    {
        return Space::query()
            ->visibleTo($user)
            ->withCount(['users', 'pages'])
            ->orderBy('name');
    }
}
