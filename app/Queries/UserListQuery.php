<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserListQuery
{
    /**
     * @return Builder<User>
     */
    public function handle(?string $q = null): Builder
    {
        $q = trim((string) $q);

        return User::query()
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id');
    }
}
