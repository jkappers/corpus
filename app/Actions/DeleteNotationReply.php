<?php

namespace App\Actions;

use App\Models\NotationReply;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteNotationReply
{
    public function handle(NotationReply $reply, User $user): void
    {
        Gate::forUser($user)->authorize('deleteReply', [$reply->notation, $reply]);

        $reply->delete();
    }
}
