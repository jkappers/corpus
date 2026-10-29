<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLog
{
    /**
     * The actor is always passed by the caller, never read from the session, so entries are correct
     * for queued, scheduled and console work too. A null actor means the system did it.
     * Metadata must never contain secrets or page content.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(AuditEvent $event, ?User $actor, ?Model $subject = null, array $metadata = []): AuditLog
    {
        $metadata += array_filter(['via' => request()->attributes->get('audit.via') ?? $this->viaToken()]);

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? $this->labelFor($subject) : null,
            'metadata' => $metadata ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 512) ?: null,
        ]);
    }

    /**
     * Writes made with a partner access token are tagged with the OAuth client.
     */
    private function viaToken(): ?string
    {
        $clientId = request()->user()?->token()?->oauth_client_id;

        return $clientId === null ? null : 'api:'.$clientId;
    }

    private function labelFor(Model $subject): string
    {
        $label = $subject->title ?? $subject->name ?? $subject->original_name ?? $subject->email
            ?? class_basename($subject).' #'.$subject->getKey();

        return mb_substr((string) $label, 0, 255);
    }
}
