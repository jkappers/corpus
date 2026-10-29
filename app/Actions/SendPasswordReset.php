<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class SendPasswordReset
{
    public function __construct(private RecordAuditLog $auditLog) {}

    public function handle(User $user, User $actor): void
    {
        Gate::forUser($actor)->authorize('sendPasswordReset', $user);

        $status = Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $this->auditLog->handle(AuditEvent::PasswordResetSent, $actor, $user);
    }
}
