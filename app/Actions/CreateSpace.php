<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CreateSpace
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Create a shared space managed by its creator.
     */
    public function handle(User $user, string $name, SpaceVisibility $visibility): Space
    {
        Gate::forUser($user)->authorize('create', Space::class);

        $space = Space::create(['name' => $name, 'visibility' => $visibility]);
        $space->users()->attach($user, ['role' => SpaceRole::Manager->value]);

        $this->auditLog->handle(AuditEvent::SpaceCreated, $user, $space);

        return $space;
    }
}
