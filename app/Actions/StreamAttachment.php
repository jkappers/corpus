<?php

namespace App\Actions;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StreamAttachment
{
    public function handle(Attachment $attachment, User $user): Response
    {
        Gate::forUser($user)->authorize('view', $attachment);

        $isPlayable = str_starts_with($attachment->mime_type, 'video/')
            || str_starts_with($attachment->mime_type, 'audio/');
        $disposition = $attachment->kind === AttachmentKind::Image || $isPlayable ? 'inline' : 'attachment';
        $headers = [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Players seek with Range requests, which only file responses honor.
        if ($isPlayable && config("filesystems.disks.{$attachment->disk}.driver") === 'local') {
            return response()
                ->file(Storage::disk($attachment->disk)->path($attachment->path), $headers)
                ->setContentDisposition($disposition, $attachment->original_name)
                ->setPrivate()
                ->setMaxAge(3600);
        }

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            $headers,
            $disposition,
        );
    }
}
