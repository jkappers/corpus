<?php

namespace App\Listeners;

use App\Actions\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class LogAuthEvent
{
    public function __construct(private RecordAuditLog $audit) {}

    public function handleLogin(Login $event): void
    {
        $this->audit->handle(AuditEvent::Login, $event->user, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->handle(
            AuditEvent::LoginFailed,
            $event->user,
            $event->user,
            ['email' => $event->credentials['email'] ?? null],
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->handle(AuditEvent::Logout, $event->user, $event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->audit->handle(AuditEvent::PasswordReset, $event->user, $event->user);
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->audit->handle(AuditEvent::TwoFactorEnabled, $event->user, $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->handle(AuditEvent::TwoFactorDisabled, $event->user, $event->user);
    }
}
