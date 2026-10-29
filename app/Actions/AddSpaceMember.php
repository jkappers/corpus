<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AddSpaceMember
{
    public function __construct(private InviteUser $inviteUser, private RecordAuditLog $auditLog) {}

    /**
     * Add the user with this email to the space, inviting them first if they have no account.
     */
    public function handle(Space $space, User $actor, string $email, SpaceRole $role): User
    {
        Gate::forUser($actor)->authorize('manageMembers', $space);

        $user = User::where('email', $email)->first()
            ?? $this->inviteUser->handle($actor, Str::before($email, '@'), $email);

        if (! $space->users()->whereKey($user->id)->exists()) {
            $space->users()->attach($user, ['role' => $role->value]);
            $space->forgetRoleCache();

            $this->auditLog->handle(AuditEvent::SpaceMemberAdded, $actor, $space, ['user_id' => $user->id, 'email' => $user->email, 'role' => $role->value]);
        }

        return $user;
    }
}
