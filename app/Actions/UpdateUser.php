<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Update name and email, and the admin role. The owner's role is fixed and admins cannot remove their own access.
     *
     * @param  array{name?: string, email?: string}  $attributes
     */
    public function handle(User $user, User $actor, array $attributes, ?string $role = null): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        if ($role !== null) {
            if (! Gate::forUser($actor)->allows('updateRole', $user)) {
                throw ValidationException::withMessages(['role' => "The owner's role cannot be changed."]);
            }

            if ($actor->is($user) && $role === 'member') {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own admin access.']);
            }
        }

        $user->fill($attributes);

        if ($role !== null) {
            $user->is_admin = $role === 'admin';
        }

        $changes = collect($user->getDirty())->except(['updated_at'])
            ->map(fn ($value, $field) => ['from' => $user->getOriginal($field), 'to' => $value])
            ->all();

        $user->save();

        if ($changes !== []) {
            $this->auditLog->handle(AuditEvent::UserUpdated, $actor, $user, ['changes' => $changes]);
        }

        return $user;
    }
}
