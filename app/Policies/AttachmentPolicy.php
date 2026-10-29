<?php

namespace App\Policies;

use App\Enums\PageType;
use App\Models\Attachment;
use App\Models\Page;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Files are embedded in markdown, so only pages take them.
     */
    public function create(User $user, Page $page): bool
    {
        return $page->type === PageType::Page && $page->space->isEditableBy($user);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->page->space->isAccessibleBy($user);
    }
}
