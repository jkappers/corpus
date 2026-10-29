<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    public function view(User $user, Space $space): bool
    {
        return $space->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Personal spaces have no settings: they are always private, owner-only, and deleted with their owner.
     */
    public function update(User $user, Space $space): bool
    {
        return ! $space->isPersonal() && $space->isManagedBy($user);
    }

    public function updateVisibility(User $user, Space $space): bool
    {
        return $user->is_admin && ! $space->isPersonal();
    }

    public function manageMembers(User $user, Space $space): bool
    {
        return ! $space->isPersonal() && $space->isManagedBy($user);
    }

    public function delete(User $user, Space $space): bool
    {
        return $user->is_admin && ! $space->isPersonal();
    }
}
