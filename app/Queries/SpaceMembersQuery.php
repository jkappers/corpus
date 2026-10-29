<?php

namespace App\Queries;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SpaceMembersQuery
{
    /**
     * @return BelongsToMany<User, Space>
     */
    public function handle(Space $space): BelongsToMany
    {
        return $space->users()->orderBy('users.name')->orderBy('users.id');
    }
}
