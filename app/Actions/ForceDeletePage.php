<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ForceDeletePage
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Permanently delete a trashed page and its attachments.
     */
    public function handle(Page $page, User $user): void
    {
        Gate::forUser($user)->authorize('forceDelete', $page);

        foreach ($page->attachments as $attachment) {
            $this->auditLog->handle(AuditEvent::AttachmentDeleted, $user, $attachment, ['page_id' => $page->id]);
        }

        $page->forceDelete();

        $this->auditLog->handle(AuditEvent::PageForceDeleted, $user, $page);
    }
}
