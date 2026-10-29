<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Queries\AuditLogQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Admin', weight: 9)]
class AuditLogController extends Controller
{
    /**
     * List audit log entries.
     */
    public function index(Request $request, AuditLogQuery $auditLogs): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'in:'.implode(',', array_column(AuditEvent::cases(), 'value'))],
            'actor' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return AuditLogResource::collection($auditLogs->handle($filters)->cursorPaginate(50)->withQueryString());
    }
}
