<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentKind;
use App\Http\Resources\V1\AttachmentResource;
use App\Queries\AttachmentListQuery;
use App\Support\PaginationProps;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FileController extends Controller
{
    public function index(Request $request, AttachmentListQuery $listAttachments): InertiaResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::enum(AttachmentKind::class)],
        ]);

        $paginator = $listAttachments
            ->handle(
                $request->user(),
                $filters['q'] ?? null,
                AttachmentKind::tryFrom($filters['kind'] ?? ''),
            )
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('files/index', [
            'files' => AttachmentResource::collection($paginator->items())->toProps(),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'kind' => $filters['kind'] ?? null,
            ],
            'pagination' => PaginationProps::from($paginator),
        ]);
    }
}
