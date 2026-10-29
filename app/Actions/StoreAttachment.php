<?php

namespace App\Actions;

use App\Enums\AttachmentKind;
use App\Enums\AuditEvent;
use App\Models\Attachment;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class StoreAttachment
{
    public function __construct(private RecordAuditLog $auditLog) {}

    public function handle(Page $page, User $user, UploadedFile $file): Attachment
    {
        Gate::forUser($user)->authorize('create', [Attachment::class, $page]);

        $disk = config('filesystems.attachments_disk');

        $attachment = $page->attachments()->make([
            'kind' => AttachmentKind::fromMimeType($file->getMimeType()),
            'disk' => $disk,
            'path' => $file->store("attachments/{$page->id}", $disk),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $attachment->user()->associate($user);
        $attachment->save();

        $this->auditLog->handle(AuditEvent::AttachmentUploaded, $user, $attachment, [
            'page_id' => $page->id,
            'size' => $attachment->size,
        ]);

        return $attachment;
    }
}
