<?php

namespace App\Actions;

use App\Models\Notation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateNotation
{
    public function __construct(private EnsureAssigneeCanAccessPage $ensureAssignee) {}

    /**
     * Edit a note's text or reassign it. The new assignee must be able to see the page.
     *
     * @param  array{body?: string, assignee_id?: int}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(Notation $notation, User $user, array $attributes): Notation
    {
        Gate::forUser($user)->authorize('update', $notation);

        if (isset($attributes['assignee_id'])) {
            $this->ensureAssignee->handle($notation->page()->withTrashed()->firstOrFail(), (int) $attributes['assignee_id']);
        }

        $notation->update(array_intersect_key($attributes, array_flip(['body', 'assignee_id'])));

        return $notation;
    }
}
