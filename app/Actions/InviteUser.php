<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates and invites a user. Authorization is the caller's job: admins invite through InviteUserAsAdmin,
 * and space managers invite through AddSpaceMember.
 */
class InviteUser
{
    public function __construct(private CreatePersonalSpace $createPersonalSpace, private RecordAuditLog $auditLog) {}

    public function handle(User $actor, string $name, string $email, bool $isAdmin = false): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);
        $user->forceFill([
            'is_admin' => $isAdmin,
            'email_verified_at' => now(),
        ])->save();

        $this->createPersonalSpace->handle($user, $actor);

        $user->notify(new UserInvitation(Password::broker(config('fortify.passwords'))->createToken($user)));

        $this->auditLog->handle(AuditEvent::UserInvited, $actor, $user, ['role' => $user->role()]);

        return $user;
    }
}
