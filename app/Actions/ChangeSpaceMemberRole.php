<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeSpaceMemberRole
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * @throws ValidationException when demoting the last manager
     */
    public function handle(Space $space, User $actor, User $user, SpaceRole $role): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $space);

        $current = $space->users()->whereKey($user->id)->first()?->pivot->role;

        abort_if($current === null, 404);

        if ($current === $role->value) {
            return;
        }

        if ($current === SpaceRole::Manager->value && $space->managerCount() <= 1) {
            throw ValidationException::withMessages(['role' => 'A space needs at least one manager.']);
        }

        $space->users()->updateExistingPivot($user->id, ['role' => $role->value]);
        $space->forgetRoleCache();

        $this->auditLog->handle(AuditEvent::SpaceMemberRoleChanged, $actor, $space, ['user_id' => $user->id, 'email' => $user->email, 'from' => $current, 'to' => $role->value]);
    }
}
