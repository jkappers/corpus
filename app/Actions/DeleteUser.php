<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteUser
{
    public function __construct(private RecordAuditLog $auditLog) {}

    public function handle(User $user, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        $this->auditLog->handle(AuditEvent::UserDeleted, $actor, $user, ['email' => $user->email]);

        $user->delete();
    }
}
