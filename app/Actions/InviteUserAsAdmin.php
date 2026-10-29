<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class InviteUserAsAdmin
{
    public function __construct(private InviteUser $inviteUser) {}

    /**
     * An administrator invites a member or another administrator.
     */
    public function handle(User $actor, string $name, string $email, bool $isAdmin = false): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return $this->inviteUser->handle($actor, $name, $email, $isAdmin);
    }
}
