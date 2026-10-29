<?php

namespace App\Actions;

use App\Models\User;

class UpdateProfile
{
    /**
     * Update the user's own name and email. A new email address must be verified again.
     *
     * @param  array{name?: string, email?: string}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $user->fill(array_intersect_key($attributes, array_flip(['name', 'email'])));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
