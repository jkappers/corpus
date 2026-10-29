<?php

namespace App\Actions;

use App\Models\Notation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetNotationCompleted
{
    public function handle(Notation $notation, User $user, bool $completed = true): Notation
    {
        Gate::forUser($user)->authorize('complete', $notation);

        $notation->forceFill([
            'completed_at' => $completed ? now() : null,
            'resolved_by_id' => $completed ? $user->id : null,
        ])->save();

        return $notation;
    }
}
