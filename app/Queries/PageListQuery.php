<?php

namespace App\Queries;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PageListQuery
{
    /**
     * Pages the user can see, as listing rows.
     *
     * @param  array{type?: ?string, space_id?: ?int, q?: ?string, trashed?: bool, sort?: ?string, direction?: ?string}  $filters
     * @return Builder<Page>
     */
    public function handle(User $user, array $filters = []): Builder
    {
        $trashed = (bool) ($filters['trashed'] ?? false);
        $sorted = isset($filters['sort']);

        return Page::query()
            ->when($trashed, fn (Builder $query) => $query->onlyTrashed())
            ->visibleTo($user)
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('type', $type))
            ->when($filters['space_id'] ?? null, fn (Builder $query, $id) => $query->where('space_id', $id))
            ->titleLike($filters['q'] ?? null)
            ->forListing()
            ->when(
                $trashed && ! $sorted,
                fn (Builder $query) => $query->orderByDesc('deleted_at')->orderByDesc('id'),
                fn (Builder $query) => $query->sortedBy($filters['sort'] ?? null, $filters['direction'] ?? null),
            );
    }
}
