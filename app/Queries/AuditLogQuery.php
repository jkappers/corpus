<?php

namespace App\Queries;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AuditLogQuery
{
    /**
     * @param  array{event?: ?string, actor?: ?int, from?: ?string, to?: ?string}  $filters
     * @return Builder<AuditLog>
     */
    public function handle(array $filters = []): Builder
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->when($filters['event'] ?? null, fn (Builder $query, $event) => $query->where('event', $event))
            ->when($filters['actor'] ?? null, fn (Builder $query, $actor) => $query->where('actor_id', $actor))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id');
    }
}
