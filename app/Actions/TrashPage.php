<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TrashPage
{
    public function __construct(private RecordAuditLog $auditLog) {}

    public function handle(Page $page, User $user): Page
    {
        Gate::forUser($user)->authorize('delete', $page);

        $page->delete();

        $this->auditLog->handle(AuditEvent::PageDeleted, $user, $page);

        return $page;
    }
}
