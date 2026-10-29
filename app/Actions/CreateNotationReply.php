<?php

namespace App\Actions;

use App\Models\Notation;
use App\Models\NotationReply;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateNotationReply
{
    /**
     * @param  array{body: string}  $attributes
     */
    public function handle(Notation $notation, User $author, array $attributes): NotationReply
    {
        Gate::forUser($author)->authorize('reply', $notation);

        $reply = $notation->replies()->make($attributes);
        $reply->author()->associate($author);
        $reply->save();

        return $reply;
    }
}
