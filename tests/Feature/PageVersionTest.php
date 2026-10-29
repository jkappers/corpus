<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_within_the_interval_create_one_version(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'v0']);
        $this->actingAs($user);

        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'v1']);
        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'v2']);

        $this->assertSame(1, $page->versions()->count());
        $this->assertSame('v0', $page->versions()->first()->body);
    }

    public function test_a_new_version_is_created_after_the_interval(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'v0']);
        $this->actingAs($user);

        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'v1']);
        $this->travel(6)->minutes();
        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'v2']);

        $this->assertSame(['v0', 'v1'], $page->versions()->oldest('id')->pluck('body')->all());
    }

    public function test_unchanged_content_is_not_snapshotted_twice(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'v0']);
        $this->actingAs($user);

        $this->patchJson(route('api.v1.pages.update', $page), ['title' => 'A']);
        $this->travel(6)->minutes();
        $this->patchJson(route('api.v1.pages.update', $page), ['title' => 'A']);
        $this->travel(6)->minutes();
        $this->patchJson(route('api.v1.pages.update', $page), ['title' => 'A']);

        $this->assertSame(2, $page->versions()->count());
    }

    public function test_old_versions_are_pruned_to_the_cap(): void
    {
        config(['audit.versions.max_per_page' => 2, 'audit.versions.interval_minutes' => 0]);
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'v0']);
        $this->actingAs($user);

        foreach (['v1', 'v2', 'v3', 'v4'] as $body) {
            $this->patchJson(route('api.v1.pages.update', $page), ['body' => $body]);
        }

        $this->assertSame(2, $page->versions()->count());
    }

    public function test_restoring_a_version_snapshots_current_state_and_logs(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'current']);
        $version = PageVersion::factory()->for($page)->create(['title' => 'Old', 'body' => 'old']);
        $this->actingAs($user);

        $this->postJson(route('api.v1.pages.versions.restore', [$page, $version]))->assertOk();

        $this->assertSame('old', $page->fresh()->body);
        $this->assertDatabaseHas('page_versions', ['page_id' => $page->id, 'reason' => 'pre-restore', 'body' => 'current']);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::VersionRestored->value]);
    }

    public function test_users_without_space_access_cannot_see_or_restore_versions(): void
    {
        $owner = User::factory()->create();
        $page = Page::factory()->for($owner)->create();
        $version = PageVersion::factory()->for($page)->create();
        $this->actingAs(User::factory()->create());

        $this->getJson(route('api.v1.pages.versions.index', $page))->assertForbidden();
        $this->postJson(route('api.v1.pages.versions.restore', [$page, $version]))->assertForbidden();
    }

    public function test_a_version_from_another_page_is_not_found(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $other = PageVersion::factory()->create();
        $this->actingAs($user);

        $this->getJson(route('api.v1.pages.versions.show', [$page, $other]))->assertNotFound();
    }
}
