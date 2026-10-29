<?php

namespace App\Actions;

use App\Models\Notation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteNotation
{
    public function handle(Notation $notation, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $notation);

        $notation->delete();
    }
}
