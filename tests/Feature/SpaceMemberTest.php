<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Models\AuditLog;
use App\Models\Space;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SpaceMemberTest extends TestCase
{
    use RefreshDatabase;

    private function spaceWithManager(): array
    {
        $manager = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$manager], SpaceRole::Manager)->create();

        return [$space, $manager];
    }

    public function test_manager_can_add_change_and_remove_members(): void
    {
        [$space, $manager] = $this->spaceWithManager();
        $other = User::factory()->create();

        $this->actingAs($manager)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => $other->email, 'role' => 'editor'])
            ->assertCreated();

        $this->assertSame('editor', $space->users()->find($other->id)->pivot->role);

        $this->actingAs($manager)
            ->patchJson(route('api.v1.spaces.members.update', [$space, $other]), ['role' => 'manager'])
            ->assertOk();

        $this->assertSame('manager', $space->users()->find($other->id)->pivot->role);

        $this->actingAs($manager)->deleteJson(route('api.v1.spaces.members.destroy', [$space, $other]));

        $this->assertFalse($space->users()->whereKey($other->id)->exists());
        $this->assertSame(3, AuditLog::whereIn('event', [
            AuditEvent::SpaceMemberAdded, AuditEvent::SpaceMemberRoleChanged, AuditEvent::SpaceMemberRemoved,
        ])->count());
    }

    public function test_plain_members_and_outsiders_cannot_manage_members(): void
    {
        [$space] = $this->spaceWithManager();
        $member = User::factory()->create();
        $space->users()->attach($member, ['role' => 'editor']);
        $target = User::factory()->create();

        foreach ([$member, User::factory()->create()] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.spaces.members.store', $space), ['email' => $target->email])
                ->assertForbidden();
            $this->actingAs($actor)
                ->patchJson(route('api.v1.spaces.members.update', [$space, $member]), ['role' => 'manager'])
                ->assertForbidden();
            $this->actingAs($actor)
                ->deleteJson(route('api.v1.spaces.members.destroy', [$space, $member]))
                ->assertForbidden();
        }
    }

    public function test_unregistered_email_is_invited_and_attached(): void
    {
        Notification::fake();
        [$space, $manager] = $this->spaceWithManager();

        $this->actingAs($manager)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => 'new@example.com']);

        $invited = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertFalse($invited->is_admin);
        $this->assertTrue($space->users()->whereKey($invited->id)->exists());
        Notification::assertSentTo($invited, UserInvitation::class);
    }

    public function test_last_manager_cannot_be_demoted_or_removed(): void
    {
        [$space, $manager] = $this->spaceWithManager();

        $this->actingAs($manager)
            ->patchJson(route('api.v1.spaces.members.update', [$space, $manager]), ['role' => 'viewer'])
            ->assertJsonValidationErrors('role');
        $this->actingAs($manager)
            ->deleteJson(route('api.v1.spaces.members.destroy', [$space, $manager]))
            ->assertJsonValidationErrors('role');

        $this->assertSame('manager', $space->users()->find($manager->id)->pivot->role);
    }

    public function test_personal_spaces_reject_member_management(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('api.v1.spaces.members.store', $admin->personalSpace), ['email' => $target->email])
            ->assertForbidden();
    }

    public function test_invalid_role_is_rejected(): void
    {
        [$space, $manager] = $this->spaceWithManager();

        $this->actingAs($manager)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => 'x@example.com', 'role' => 'owner'])
            ->assertJsonValidationErrors('role');
    }
}
