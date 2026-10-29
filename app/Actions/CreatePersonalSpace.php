<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;

class CreatePersonalSpace
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Give a new user their private space. The actor is whoever created the account: the user on sign-up, or the inviting admin or manager.
     */
    public function handle(User $user, ?User $actor = null): Space
    {
        $space = Space::create([
            'name' => $user->name."'s space",
            'visibility' => SpaceVisibility::Private,
        ]);

        $space->owner()->associate($user)->save();
        $space->users()->attach($user, ['role' => SpaceRole::Manager->value]);
        $space->forgetRoleCache();

        $this->auditLog->handle(AuditEvent::SpaceCreated, $actor ?? $user, $space);

        return $space;
    }
}
