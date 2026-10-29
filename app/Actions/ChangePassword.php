<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\User;

class ChangePassword
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Set the user's own password. The caller confirms the current password first.
     */
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);

        $this->auditLog->handle(AuditEvent::PasswordChanged, $user, $user);
    }
}
