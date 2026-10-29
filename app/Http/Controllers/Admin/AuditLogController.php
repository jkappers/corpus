<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Queries\AuditLogQuery;
use App\Support\PaginationProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogQuery $auditLogs): InertiaResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'in:'.implode(',', array_column(AuditEvent::cases(), 'value'))],
            'actor' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $paginator = $auditLogs->handle($filters)->paginate(50)->withQueryString();

        return Inertia::render('admin/audit-log/index', [
            'logs' => AuditLogResource::collection($paginator->getCollection())->toProps(),
            'pagination' => PaginationProps::from($paginator),
            'filters' => [
                'event' => $filters['event'] ?? '',
                'actor' => $filters['actor'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }
}
