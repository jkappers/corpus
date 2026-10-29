<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admins_cannot_view_the_audit_log(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.audit-log'))->assertForbidden();
    }

    public function test_admins_can_view_and_filter_the_audit_log(): void
    {
        $admin = $this->admin();
        AuditLog::factory()->create(['event' => AuditEvent::PageDeleted]);
        AuditLog::factory()->create(['event' => AuditEvent::Login]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log', ['event' => 'page.deleted']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-log/index')
                ->has('logs', 1)
                ->where('logs.0.event', 'page.deleted'));
    }

    public function test_page_lifecycle_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $pageId = $this->postJson(route('api.v1.pages.store'))->json('data.id');
        $this->deleteJson(route('api.v1.pages.destroy', $pageId));
        $this->postJson(route('api.v1.pages.restore', $pageId));

        $events = AuditLog::pluck('event')->all();
        $this->assertContains(AuditEvent::PageCreated, $events);
        $this->assertContains(AuditEvent::PageDeleted, $events);
        $this->assertContains(AuditEvent::PageRestored, $events);
        $this->assertSame($user->id, AuditLog::where('event', AuditEvent::PageDeleted)->first()->actor_id);
    }

    public function test_autosave_edits_collapse_into_one_entry_without_content(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $this->actingAs($user);

        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'secret one'])->assertOk();
        $this->patchJson(route('api.v1.pages.update', $page), ['body' => 'secret two', 'title' => 'New'])->assertOk();

        $logs = AuditLog::where('event', AuditEvent::PageUpdated)->get();
        $this->assertCount(1, $logs);
        $this->assertEqualsCanonicalizing(['body', 'title'], $logs->first()->metadata['fields']);
        $this->assertStringNotContainsString('secret', json_encode($logs->first()->metadata));
    }

    public function test_inviting_and_deleting_users_is_logged(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->postJson(route('api.v1.admin.users.store'), ['name' => 'New', 'email' => 'new@example.com', 'role' => 'member']);
        Notification::assertSentTo(User::where('email', 'new@example.com')->first(), UserInvitation::class);
        $invited = User::where('email', 'new@example.com')->first();

        $this->postJson(route('api.v1.admin.users.password-reset', User::factory()->create()));
        $this->deleteJson(route('api.v1.admin.users.destroy', $invited));

        $events = AuditLog::pluck('event')->all();
        $this->assertContains(AuditEvent::UserInvited, $events);
        $this->assertContains(AuditEvent::PasswordResetSent, $events);
        $this->assertContains(AuditEvent::UserDeleted, $events);
        $this->assertNull(AuditLog::where('event', AuditEvent::UserDeleted)->first()->metadata['password'] ?? null);
    }

    public function test_login_and_failed_login_are_logged(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::LoginFailed->value]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::Login->value, 'actor_id' => $user->id]);
    }

    public function test_entries_survive_actor_deletion(): void
    {
        $log = AuditLog::factory()->create();

        $log->actor->delete();

        $this->assertNull($log->fresh()->actor_id);
    }
}
