<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditEvent;
use App\Enums\SpaceRole;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use App\Support\ApiScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_visible_spaces_with_abilities(): void
    {
        $user = User::factory()->create();
        $hidden = Space::factory()->private()->create();

        $response = $this->actingAs($user)->getJson(route('api.v1.spaces.index'))->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($user->personalSpace->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
        $response->assertJsonStructure(['data' => [['id', 'name', 'visibility', 'can_edit', 'can_manage', 'can_update', 'can_delete']]]);
    }

    public function test_personal_space_settings_are_forbidden_with_an_access_token(): void
    {
        $admin = User::factory()->admin()->create();
        $space = $admin->personalSpace;
        Passport::actingAs($admin, ApiScopes::all());
        $this->withToken('test');

        $this->getJson(route('api.v1.spaces.show', $space))->assertOk()->assertJsonPath('data.can_update', false);
        $this->patchJson(route('api.v1.spaces.update', $space), ['name' => 'Renamed'])->assertForbidden();
        $this->deleteJson(route('api.v1.spaces.destroy', $space))->assertForbidden();
        $this->postJson(route('api.v1.spaces.content.transfer', $space), ['target_space_id' => Space::factory()->create()->id])->assertForbidden();
        $this->deleteJson(route('api.v1.spaces.content.destroy', $space), ['confirmation' => 'I accept the consequences'])->assertForbidden();
        $this->getJson(route('api.v1.spaces.members.index', $space))->assertForbidden();
        $this->postJson(route('api.v1.spaces.members.store', $space), ['email' => User::factory()->create()->email])->assertForbidden();
        $this->patchJson(route('api.v1.spaces.members.update', [$space, $admin]), ['role' => 'viewer'])->assertForbidden();
        $this->deleteJson(route('api.v1.spaces.members.destroy', [$space, $admin]))->assertForbidden();

        $this->assertSame("{$admin->name}'s space", $space->fresh()->name);
        $this->assertSame([$admin->id], $space->users()->pluck('users.id')->all());
    }

    public function test_show_includes_members_only_for_managers(): void
    {
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->create();
        $space->users()->attach($manager, ['role' => SpaceRole::Manager->value]);
        $space->users()->attach($viewer, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($manager)->getJson(route('api.v1.spaces.show', $space))
            ->assertOk()
            ->assertJsonCount(2, 'data.members');

        $this->actingAs($viewer)->getJson(route('api.v1.spaces.show', $space))
            ->assertOk()
            ->assertJsonMissingPath('data.members');
    }

    public function test_show_forbids_non_members_of_a_private_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.spaces.show', Space::factory()->private()->create()))
            ->assertForbidden();
    }

    public function test_only_admins_can_create_spaces(): void
    {
        $payload = ['name' => 'Team', 'visibility' => 'open'];

        $this->actingAs(User::factory()->create())->postJson(route('api.v1.spaces.store'), $payload)->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('api.v1.spaces.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Team');

        $this->assertSame(SpaceRole::Manager->value, Space::where('name', 'Team')->sole()->roleFor($admin)->value);
    }

    public function test_managers_can_add_change_and_remove_members(): void
    {
        $manager = User::factory()->create();
        $space = Space::factory()->private()->create();
        $space->users()->attach($manager, ['role' => SpaceRole::Manager->value]);
        $member = User::factory()->create();

        $this->actingAs($manager)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => $member->email, 'role' => 'viewer'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'viewer');

        $this->patchJson(route('api.v1.spaces.members.update', [$space, $member]), ['role' => 'editor'])
            ->assertOk()
            ->assertJsonPath('data.role', 'editor');

        $this->deleteJson(route('api.v1.spaces.members.destroy', [$space, $member]))->assertNoContent();
        $this->assertFalse($space->users()->whereKey($member->id)->exists());
    }

    public function test_demoting_or_removing_the_last_manager_returns_422(): void
    {
        $manager = User::factory()->create();
        $space = Space::factory()->private()->create();
        $space->users()->attach($manager, ['role' => SpaceRole::Manager->value]);

        $this->actingAs($manager)
            ->patchJson(route('api.v1.spaces.members.update', [$space, $manager]), ['role' => 'editor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->deleteJson(route('api.v1.spaces.members.destroy', [$space, $manager]))
            ->assertUnprocessable();
    }

    public function test_editors_cannot_manage_members(): void
    {
        $editor = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$editor])->create();

        $this->actingAs($editor)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => 'x@example.com', 'role' => 'viewer'])
            ->assertForbidden();
    }

    public function test_destroy_is_admin_only(): void
    {
        $space = Space::factory()->create();

        $this->actingAs(User::factory()->create())->deleteJson(route('api.v1.spaces.destroy', $space))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->deleteJson(route('api.v1.spaces.destroy', $space))->assertNoContent();
    }

    public function test_space_with_content_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $space = Space::factory()->create();
        Page::factory()->for($admin)->create(['space_id' => $space->id, 'deleted_at' => now()]);

        $this->actingAs($admin)->deleteJson(route('api.v1.spaces.destroy', $space))->assertUnprocessable();
        $this->assertModelExists($space);

        Page::withTrashed()->where('space_id', $space->id)->forceDelete();

        $this->actingAs($admin)->deleteJson(route('api.v1.spaces.destroy', $space))->assertNoContent();
    }

    public function test_delete_content_requires_the_confirmation_phrase(): void
    {
        $admin = User::factory()->admin()->create();
        $space = Space::factory()->create();
        $live = Page::factory()->for($admin)->create(['space_id' => $space->id]);
        $trashed = Page::factory()->for($admin)->create(['space_id' => $space->id, 'deleted_at' => now()]);
        $other = Page::factory()->for($admin)->create();

        $this->actingAs($admin)->deleteJson(route('api.v1.spaces.content.destroy', $space), ['confirmation' => 'nope'])
            ->assertJsonValidationErrors('confirmation');
        $this->assertModelExists($live);

        $this->actingAs($admin)
            ->deleteJson(route('api.v1.spaces.content.destroy', $space), ['confirmation' => 'I accept the consequences'])
            ->assertNoContent();

        $this->assertModelMissing($live);
        $this->assertNull(Page::withTrashed()->find($trashed->id));
        $this->assertModelExists($other);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::SpaceContentDeleted->value, 'subject_id' => $space->id]);
    }

    public function test_viewers_cannot_delete_or_transfer_content(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->create();
        $space->users()->attach($viewer, ['role' => SpaceRole::Viewer->value]);
        $page = Page::factory()->for($viewer)->create(['space_id' => $space->id]);
        $target = Space::factory()->create();

        $this->actingAs($viewer)
            ->deleteJson(route('api.v1.spaces.content.destroy', $space), ['confirmation' => 'I accept the consequences'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->postJson(route('api.v1.spaces.content.transfer', $space), ['target_space_id' => $target->id])
            ->assertForbidden();
        $this->assertSame($space->id, $page->fresh()->space_id);
    }

    public function test_transfer_moves_live_and_trashed_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $space = Space::factory()->create();
        $target = Space::factory()->create();
        $live = Page::factory()->for($admin)->create(['space_id' => $space->id]);
        $trashed = Page::factory()->for($admin)->create(['space_id' => $space->id, 'deleted_at' => now()]);

        $this->actingAs($admin)
            ->postJson(route('api.v1.spaces.content.transfer', $space), ['target_space_id' => $target->id])
            ->assertOk()
            ->assertJson(['moved' => 2]);

        $this->assertSame($target->id, $live->fresh()->space_id);
        $this->assertSame($target->id, Page::withTrashed()->find($trashed->id)->space_id);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::SpaceContentTransferred->value, 'subject_id' => $space->id]);
    }

    public function test_transfer_rejects_the_same_or_an_uneditable_space(): void
    {
        $manager = User::factory()->create();
        $space = Space::factory()->create();
        $space->users()->attach($manager, ['role' => SpaceRole::Manager->value]);
        $locked = Space::factory()->private()->create();

        foreach ([$space->id, $locked->id] as $targetId) {
            $this->actingAs($manager)
                ->postJson(route('api.v1.spaces.content.transfer', $space), ['target_space_id' => $targetId])
                ->assertJsonValidationErrors('target_space_id');
        }
    }
}
