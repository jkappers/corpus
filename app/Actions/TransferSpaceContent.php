<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferSpaceContent
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Move every page in the space, trashed ones included, to another space the user can edit.
     *
     * @return int the number of pages moved
     *
     * @throws ValidationException
     */
    public function handle(Space $space, User $user, int $targetSpaceId): int
    {
        Gate::forUser($user)->authorize('update', $space);

        $target = Space::query()->editableBy($user)->whereKeyNot($space->id)->find($targetSpaceId);

        if ($target === null) {
            throw ValidationException::withMessages(['target_space_id' => 'The selected space is invalid.']);
        }

        $moved = DB::transaction(fn () => Page::withTrashed()
            ->where('space_id', $space->id)
            ->update(['space_id' => $target->id]));

        $this->auditLog->handle(AuditEvent::SpaceContentTransferred, $user, $space, [
            'to_space_id' => $target->id,
            'pages' => $moved,
        ]);

        return $moved;
    }
}
