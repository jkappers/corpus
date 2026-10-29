<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_non_admins_cannot_access_user_management(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->postJson(route('api.v1.admin.users.store'), [])->assertForbidden();
        $this->get(route('admin.users.edit', $other))->assertForbidden();
        $this->patchJson(route('api.v1.admin.users.update', $other), ['name' => 'x'])->assertForbidden();
        $this->deleteJson(route('api.v1.admin.users.destroy', $other))->assertForbidden();

        $this->assertModelExists($other);
    }

    public function test_admin_can_list_and_search_users(): void
    {
        User::factory()->create(['name' => 'Alice Zed']);
        User::factory()->create(['name' => 'Bob Yan']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.index', ['q' => 'Alice']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users', 1)
                ->where('users.0.name', 'Alice Zed'));
    }

    public function test_admin_can_create_a_verified_user_with_a_personal_space(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.v1.admin.users.store'), [
                'name' => 'New Person',
                'email' => 'new@example.com',
                'role' => 'admin',
            ])
            ->assertCreated();

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->personalSpace->isPrivate());
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_invited_user_can_set_a_password_from_the_emailed_link(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.v1.admin.users.store'), ['name' => 'New Person', 'email' => 'new@example.com']);
        auth()->logout();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $token = null;
        Notification::assertSentTo($user, UserInvitation::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-strong-password-1', $user->refresh()->password));
    }

    public function test_admin_can_send_a_password_reset_email(): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.v1.admin.users.password-reset', $target))
            ->assertNoContent();

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_non_admins_cannot_send_a_password_reset_email(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.admin.users.password-reset', User::factory()->create()))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_create_validates_unique_email(): void
    {
        $existing = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.v1.admin.users.store'), [
                'name' => 'Dup',
                'email' => $existing->email,
            ])
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_a_user_without_changing_the_password(): void
    {
        $target = User::factory()->create();
        $hash = $target->password;

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('api.v1.admin.users.update', $target), [
                'name' => 'Renamed',
                'email' => $target->email,
                'password' => '',
                'role' => 'admin',
            ])
            ->assertOk();

        $target->refresh();

        $this->assertSame('Renamed', $target->name);
        $this->assertTrue($target->is_admin);
        $this->assertSame($hash, $target->password);
    }

    public function test_admin_cannot_set_a_users_password_directly(): void
    {
        $target = User::factory()->create();
        $original = $target->password;

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('api.v1.admin.users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $this->assertSame($original, $target->refresh()->password);
    }

    public function test_admin_cannot_remove_their_own_admin_access(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson(route('api.v1.admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'member',
            ])
            ->assertJsonValidationErrors('role');

        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_admin_can_delete_another_user_but_not_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.admin.users.destroy', $target))
            ->assertNoContent();

        $this->assertModelMissing($target);

        $this->deleteJson(route('api.v1.admin.users.destroy', $admin))->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_admin_can_change_the_role_of_other_admins_and_members(): void
    {
        $actor = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($actor);

        $this->patchJson(route('api.v1.admin.users.update', $otherAdmin), [
            'name' => $otherAdmin->name,
            'email' => $otherAdmin->email,
            'role' => 'member',
        ])->assertOk();

        $this->patchJson(route('api.v1.admin.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'admin',
        ])->assertOk();

        $this->assertFalse($otherAdmin->refresh()->is_admin);
        $this->assertTrue($member->refresh()->is_admin);
    }

    public function test_user_listing_reports_roles_and_delete_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('users', fn ($users) => collect($users)->every(fn ($user) => match ($user['id']) {
                    $admin->id => $user['role'] === 'admin' && $user['can_delete'] === false,
                    $owner->id => $user['role'] === 'owner' && $user['can_delete'] === false,
                    default => true,
                })));
    }

    public function test_edit_form_reports_whether_the_role_can_be_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $member))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('managed.can_update_role', true));

        $this->get(route('admin.users.edit', $admin))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('managed.can_update_role', false));
    }

    public function test_nobody_can_change_the_owners_role(): void
    {
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson(route('api.v1.admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'member',
            ])
            ->assertJsonValidationErrors('role');

        $owner->refresh();

        $this->assertTrue($owner->is_admin);
        $this->assertTrue($owner->is_owner);
    }

    public function test_admin_can_still_rename_the_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('api.v1.admin.users.update', $owner), [
                'name' => 'Renamed Owner',
                'email' => $owner->email,
            ])
            ->assertOk();

        $this->assertSame('Renamed Owner', $owner->refresh()->name);
    }

    public function test_owner_can_change_admin_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->patchJson(route('api.v1.admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'member',
            ])
            ->assertOk();

        $this->assertFalse($admin->refresh()->is_admin);
    }

    public function test_owner_role_cannot_be_assigned(): void
    {
        $member = User::factory()->create();

        $this->actingAs(User::factory()->owner()->create())
            ->patchJson(route('api.v1.admin.users.update', $member), [
                'name' => $member->name,
                'email' => $member->email,
                'role' => 'owner',
            ])
            ->assertJsonValidationErrors('role');

        $this->assertFalse($member->refresh()->is_owner);
    }

    public function test_owner_cannot_be_deleted(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson(route('api.v1.admin.users.destroy', $owner))
            ->assertForbidden();

        $this->assertModelExists($owner);
    }
}
