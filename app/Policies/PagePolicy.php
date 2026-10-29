<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\Space;
use App\Models\User;

class PagePolicy
{
    public function create(User $user, Space $space): bool
    {
        return $space->isEditableBy($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $page->space->isAccessibleBy($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $page->space->isEditableBy($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $page->space->isEditableBy($user);
    }

    public function restore(User $user, Page $page): bool
    {
        return $page->space->isEditableBy($user);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $page->space->isEditableBy($user);
    }
}
