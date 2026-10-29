<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteSpaceContent
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Permanently delete every page in the space, trashed ones included, with their attachments.
     */
    public function handle(Space $space, User $user): int
    {
        Gate::forUser($user)->authorize('update', $space);

        $deleted = 0;

        $space->pages()->withTrashed()->with('attachments')->chunkById(100, function ($pages) use ($user, &$deleted) {
            foreach ($pages as $page) {
                foreach ($page->attachments as $attachment) {
                    $this->auditLog->handle(AuditEvent::AttachmentDeleted, $user, $attachment, ['page_id' => $page->id]);
                }

                $page->forceDelete();
                $deleted++;
            }
        });

        $this->auditLog->handle(AuditEvent::SpaceContentDeleted, $user, $space, ['pages' => $deleted]);

        return $deleted;
    }
}
