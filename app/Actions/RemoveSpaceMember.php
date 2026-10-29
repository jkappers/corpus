<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RemoveSpaceMember
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * @throws ValidationException when removing the last manager
     */
    public function handle(Space $space, User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $space);

        $current = $space->users()->whereKey($user->id)->first()?->pivot->role;

        if ($current === null) {
            return;
        }

        if ($current === SpaceRole::Manager->value && $space->managerCount() <= 1) {
            throw ValidationException::withMessages(['role' => 'A space needs at least one manager.']);
        }

        $space->users()->detach($user);
        $space->forgetRoleCache();

        $this->auditLog->handle(AuditEvent::SpaceMemberRemoved, $actor, $space, ['user_id' => $user->id, 'email' => $user->email]);
    }
}
