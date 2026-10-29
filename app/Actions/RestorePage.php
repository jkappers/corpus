<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RestorePage
{
    public function __construct(private RecordAuditLog $auditLog) {}

    public function handle(Page $page, User $user): Page
    {
        Gate::forUser($user)->authorize('restore', $page);

        $page->restore();

        $this->auditLog->handle(AuditEvent::PageRestored, $user, $page);

        return $page->refresh();
    }
}
