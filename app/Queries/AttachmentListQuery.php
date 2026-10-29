<?php

namespace App\Queries;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AttachmentListQuery
{
    /**
     * Files the user can see, newest first.
     *
     * @return Builder<Attachment>
     */
    public function handle(User $user, ?string $q = null, ?AttachmentKind $kind = null, ?int $spaceId = null): Builder
    {
        return Attachment::query()
            ->visibleTo($user)
            ->named($q)
            ->when($kind, fn (Builder $query) => $query->where('kind', $kind))
            ->when($spaceId, fn (Builder $query, int $id) => $query->whereHas('page', fn (Builder $pages) => $pages->where('space_id', $id)))
            ->with(['page:id,title,type', 'user:id,name'])
            ->latest()
            ->orderByDesc('id');
    }
}
