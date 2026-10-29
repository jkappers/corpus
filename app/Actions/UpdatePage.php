<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Exceptions\StaleWriteException;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdatePage
{
    public function __construct(private SnapshotPage $snapshot, private RecordAuditLog $auditLog) {}

    /**
     * Snapshot the page, then apply the given changes (title, body, data, space_id).
     * With `$baseUpdatedAt` the write is refused if the page changed since; with `$append` the body is added after the existing one.
     *
     * @param  array{title?: ?string, body?: ?string, data?: ?array<string, mixed>, space_id?: int}  $attributes
     *
     * @throws StaleWriteException
     * @throws ValidationException
     */
    public function handle(Page $page, User $user, array $attributes, ?string $baseUpdatedAt = null, bool $append = false): Page
    {
        Gate::forUser($user)->authorize('update', $page);

        $page->type->ensureAcceptsContent($attributes);

        if (isset($attributes['space_id']) && ! $this->canMoveInto((int) $attributes['space_id'], $user)) {
            throw ValidationException::withMessages(['space_id' => 'The selected space is invalid.']);
        }

        if ($baseUpdatedAt !== null && ! Carbon::parse($baseUpdatedAt)->startOfSecond()->equalTo($page->updated_at->startOfSecond())) {
            throw new StaleWriteException($page->updated_at);
        }

        if ($append && isset($attributes['body'])) {
            $attributes['body'] = rtrim((string) $page->body)."\n\n".$attributes['body'];
        }

        $this->snapshot->handle($page, $user);

        $page->fill(array_intersect_key($attributes, array_flip(['title', 'body', 'data'])));

        if (array_key_exists('space_id', $attributes)) {
            $page->space_id = (int) $attributes['space_id'];
        }

        if ($page->title === null || trim($page->title) === '') {
            $page->title = Page::UNTITLED;
        }

        $fromSpaceId = $page->getOriginal('space_id');

        $page->save();

        $this->audit($page, $user, $fromSpaceId);

        return $page;
    }

    private function canMoveInto(int $spaceId, User $user): bool
    {
        return Space::query()->editableBy($user)->whereKey($spaceId)->exists();
    }

    /**
     * Autosave writes constantly, so edits by one actor collapse into a single entry per window.
     */
    private function audit(Page $page, User $user, int $fromSpaceId): void
    {
        $changed = array_values(array_diff(array_keys($page->getChanges()), ['updated_at']));

        if (in_array('space_id', $changed, true)) {
            $this->auditLog->handle(AuditEvent::PageMoved, $user, $page, [
                'from_space_id' => $fromSpaceId,
                'to_space_id' => $page->space_id,
            ]);
        }

        $fields = array_values(array_diff($changed, ['space_id']));

        if ($fields === []) {
            return;
        }

        $recent = AuditLog::query()
            ->where('event', AuditEvent::PageUpdated)
            ->where('subject_type', $page->getMorphClass())
            ->where('subject_id', $page->getKey())
            ->where('actor_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(config('audit.page_update_window_minutes')))
            ->latest('id')
            ->first();

        if ($recent === null) {
            $this->auditLog->handle(AuditEvent::PageUpdated, $user, $page, ['fields' => $fields]);

            return;
        }

        $recent->update([
            'subject_label' => mb_substr($page->title, 0, 255),
            'metadata' => ['fields' => array_values(array_unique([...($recent->metadata['fields'] ?? []), ...$fields]))],
        ]);
    }
}
