<?php

namespace App\Actions;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;

class SnapshotPage
{
    /**
     * Store the page's current persisted state as a version.
     *
     * Automatic snapshots are throttled and skipped when content is unchanged.
     */
    public function handle(Page $page, ?User $user = null, string $reason = 'autosave'): ?PageVersion
    {
        $latest = $page->versions()->latest('id')->first();

        if ($reason === 'autosave' && $latest
            && $latest->created_at->gt(now()->subMinutes(config('audit.versions.interval_minutes')))) {
            return null;
        }

        if ($latest && $this->sameContent($latest, $page)) {
            return null;
        }

        $version = $page->versions()->make([
            'title' => $page->title,
            'body' => $page->body,
            'data' => $page->data,
            'reason' => $reason,
        ]);
        $version->user()->associate($user);
        $version->save();

        $this->prune($page);

        return $version;
    }

    private function sameContent(PageVersion $version, Page $page): bool
    {
        return $version->title === $page->title
            && $version->body === $page->body
            && $version->data === $page->data;
    }

    private function prune(Page $page): void
    {
        $keep = $page->versions()->latest('id')->limit(config('audit.versions.max_per_page'))->pluck('id');

        $page->versions()->whereNotIn('id', $keep)->delete();
    }
}
