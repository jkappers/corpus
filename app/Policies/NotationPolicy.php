<?php

namespace App\Policies;

use App\Enums\PageType;
use App\Models\Notation;
use App\Models\NotationReply;
use App\Models\Page;
use App\Models\User;

class NotationPolicy
{
    /**
     * Notes anchor to markdown text, so only pages take them.
     */
    public function create(User $user, Page $page): bool
    {
        return $page->type === PageType::Page && $page->space->isEditableBy($user);
    }

    public function update(User $user, Notation $notation): bool
    {
        return $this->involves($user, $notation);
    }

    public function complete(User $user, Notation $notation): bool
    {
        return $this->involves($user, $notation);
    }

    /**
     * Anyone who can write on the page may reply, but only while the thread is open.
     */
    public function reply(User $user, Notation $notation): bool
    {
        return $notation->completed_at === null
            && $this->canAccess($user, $notation)
            && ($notation->page?->space->isEditableBy($user) ?? false);
    }

    public function deleteReply(User $user, Notation $notation, NotationReply $reply): bool
    {
        return $this->canAccess($user, $notation)
            && ($reply->author_id === $user->id || $user->is_admin);
    }

    public function delete(User $user, Notation $notation): bool
    {
        return $this->canAccess($user, $notation)
            && ($notation->author_id === $user->id || $user->is_admin);
    }

    private function involves(User $user, Notation $notation): bool
    {
        return $this->canAccess($user, $notation)
            && ($notation->author_id === $user->id || $notation->assignee_id === $user->id);
    }

    private function canAccess(User $user, Notation $notation): bool
    {
        $page = $notation->relationLoaded('page')
            ? $notation->page
            : $notation->page()->withTrashed()->first();

        return $page?->space->isAccessibleBy($user) ?? false;
    }
}
