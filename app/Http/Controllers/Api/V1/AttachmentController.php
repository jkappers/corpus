<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StoreAttachment;
use App\Actions\StreamAttachment;
use App\Enums\AttachmentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\V1\AttachmentResource;
use App\Models\Attachment;
use App\Models\Page;
use App\Queries\AttachmentListQuery;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Attachments', 'Files embedded in pages.', weight: 5)]
class AttachmentController extends Controller
{
    /**
     * List files.
     */
    public function index(Request $request, AttachmentListQuery $listAttachments): AnonymousResourceCollection
    {
        $kind = AttachmentKind::tryFrom((string) $request->query('kind', ''));

        $files = $listAttachments->handle($request->user(), $request->query('q'), $kind)
            ->cursorPaginate(50)
            ->withQueryString();

        return AttachmentResource::collection($files);
    }

    /**
     * Get a file.
     */
    public function show(Attachment $attachment): AttachmentResource
    {
        Gate::authorize('view', $attachment);

        return new AttachmentResource($attachment->load(['page:id,title,type', 'user:id,name']));
    }

    /**
     * Download a file.
     */
    public function content(Request $request, Attachment $attachment, StreamAttachment $stream): SymfonyResponse
    {
        return $stream->handle($attachment, $request->user());
    }

    /**
     * Upload a file to a page.
     */
    public function store(StoreAttachmentRequest $request, Page $page, StoreAttachment $storeAttachment): JsonResponse
    {
        $attachment = $storeAttachment->handle($page, $request->user(), $request->file('file'));

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }
}
