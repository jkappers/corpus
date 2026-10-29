<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateSpace
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Rename the space or change its visibility (admins only). Personal spaces are always private.
     *
     * @param  array{name?: string, visibility?: SpaceVisibility|string}  $attributes
     */
    public function handle(Space $space, User $user, array $attributes): Space
    {
        Gate::forUser($user)->authorize('update', $space);

        $space->fill(array_intersect_key($attributes, array_flip(['name', 'visibility'])));

        if ($space->isDirty('visibility')) {
            Gate::forUser($user)->authorize('updateVisibility', $space);
        }

        $space->save();

        $fields = array_values(array_diff(array_keys($space->getChanges()), ['updated_at']));

        if ($fields !== []) {
            $this->auditLog->handle(AuditEvent::SpaceUpdated, $user, $space, ['fields' => $fields]);
        }

        return $space;
    }
}
