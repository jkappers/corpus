<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class RestorePageVersion
{
    public function __construct(private SnapshotPage $snapshot, private RecordAuditLog $auditLog) {}

    /**
     * Snapshot the current state as "pre-restore", then overwrite the page from the version.
     *
     * @throws ModelNotFoundException when the version belongs to another page
     */
    public function handle(Page $page, PageVersion $version, User $user): Page
    {
        Gate::forUser($user)->authorize('update', $page);

        if ($version->page_id !== $page->id) {
            throw (new ModelNotFoundException)->setModel(PageVersion::class, [$version->id]);
        }

        $this->snapshot->handle($page, $user, 'pre-restore');

        $page->forceFill($version->only(['title', 'body', 'data']))->save();

        $this->auditLog->handle(AuditEvent::VersionRestored, $user, $page, ['version_id' => $version->id]);

        return $page;
    }
}
