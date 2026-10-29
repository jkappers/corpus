<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteSpace
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * @throws ValidationException when the space still has content
     */
    public function handle(Space $space, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $space);

        if ($reason = $space->deletionLockedReason()) {
            throw ValidationException::withMessages(['space' => $reason]);
        }

        $space->delete();

        $this->auditLog->handle(AuditEvent::SpaceDeleted, $user, $space);
    }
}
