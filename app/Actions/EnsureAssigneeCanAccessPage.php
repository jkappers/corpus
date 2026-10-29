<?php

namespace App\Actions;

use App\Models\Page;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EnsureAssigneeCanAccessPage
{
    /**
     * @throws ValidationException when the user does not exist or cannot see the page
     */
    public function handle(Page $page, int $assigneeId): void
    {
        $assignee = User::find($assigneeId);

        if ($assignee === null || ! $page->space->isAccessibleBy($assignee)) {
            throw ValidationException::withMessages(['assignee_id' => 'The assignee cannot access this page.']);
        }
    }
}
