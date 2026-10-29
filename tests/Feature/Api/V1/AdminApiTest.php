<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user);

        $this->getJson(route('api.v1.admin.users.index'))->assertForbidden();
        $this->postJson(route('api.v1.admin.users.store'), ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        $this->deleteJson(route('api.v1.admin.users.destroy', $other))->assertForbidden();
        $this->getJson(route('api.v1.admin.audit-log.index'))->assertForbidden();
    }

    public function test_admin_lists_users_with_roles_and_abilities(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Aaron Admin']);
        $owner = User::factory()->owner()->create(['name' => 'Olive Owner']);

        $rows = $this->actingAs($admin)->getJson(route('api.v1.admin.users.index', ['q' => 'O']))
            ->assertOk()
            ->json('data');

        $olive = collect($rows)->firstWhere('id', $owner->id);
        $this->assertSame('owner', $olive['role']);
        $this->assertFalse($olive['can_delete']);
        $this->assertFalse($olive['can_update_role']);
    }

    public function test_admin_invites_updates_and_deletes_a_user(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $id = $this->postJson(route('api.v1.admin.users.store'), ['name' => 'New', 'email' => 'new@example.com', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->json('data.id');

        Notification::assertSentTo(User::find($id), UserInvitation::class);

        $this->patchJson(route('api.v1.admin.users.update', $id), ['name' => 'Renamed', 'email' => 'new@example.com', 'role' => 'member'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.role', 'member');

        $this->deleteJson(route('api.v1.admin.users.destroy', $id))->assertNoContent();
        $this->assertModelMissing(User::find($id) ?? new User(['id' => $id]));
    }

    public function test_admin_cannot_delete_themselves_or_change_the_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin);

        $this->deleteJson(route('api.v1.admin.users.destroy', $admin))->assertForbidden();
        $this->patchJson(route('api.v1.admin.users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'member'])
            ->assertJsonValidationErrors('role');
    }

    public function test_admin_sends_a_password_reset(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->postJson(route('api.v1.admin.users.password-reset', $target))->assertNoContent();

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_audit_log_is_filterable(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->create(['event' => 'page.deleted']);
        AuditLog::factory()->create(['event' => 'auth.login']);

        $this->actingAs($admin)->getJson(route('api.v1.admin.audit-log.index', ['event' => 'page.deleted']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_label', 'Page deleted');
    }
}
