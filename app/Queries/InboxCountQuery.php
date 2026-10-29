<?php

namespace App\Queries;

use App\Models\Notation;
use App\Models\User;

class InboxCountQuery
{
    public function handle(User $user): int
    {
        return Notation::query()->inboxFor($user)->count();
    }
}
