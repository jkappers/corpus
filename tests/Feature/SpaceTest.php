<?php

namespace Tests\Feature;

use App\Enums\SpaceVisibility;
use App\Http\Resources\V1\SpaceResource;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_private_personal_space(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $space = User::where('email', 'jane@example.com')->firstOrFail()->personalSpace;

        $this->assertSame(SpaceVisibility::Private, $space->visibility);
        $this->assertCount(1, $space->users);
    }

    public function test_database_rejects_a_non_private_personal_space(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite does not get the check constraint.');
        }

        $owner = User::factory()->create();

        $this->expectException(QueryException::class);

        Space::factory()->create(['owner_id' => $owner->id, 'visibility' => SpaceVisibility::Open]);
    }

    public function test_locked_visibility_reason_is_exposed_to_clients(): void
    {
        $user = User::factory()->create();
        $shared = Space::factory()->create();

        $this->actingAs($user);

        $this->assertSame('Personal spaces are always private.', (new SpaceResource($user->personalSpace))->resolve(request())['visibility_locked_reason']);
        $this->assertNull((new SpaceResource($shared))->resolve(request())['visibility_locked_reason']);
    }

    public function test_locked_membership_reason_is_exposed_to_clients(): void
    {
        $user = User::factory()->create();
        $shared = Space::factory()->create();

        $this->actingAs($user);

        $this->assertSame('Personal spaces belong only to you.', (new SpaceResource($user->personalSpace))->resolve(request())['membership_locked_reason']);
        $this->assertNull((new SpaceResource($shared))->resolve(request())['membership_locked_reason']);
    }

    public function test_private_space_pages_are_hidden_from_non_members(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->private()->create();
        $space->users()->attach($member);
        $page = Page::factory()->for($member)->create(['space_id' => $space->id]);

        $this->actingAs($outsider)
            ->get(route('home'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('pages', 0)->has('spaces', 1));

        $this->actingAs($outsider)->get(route('pages.show', $page))->assertForbidden();
        $this->actingAs($outsider)->patchJson(route('api.v1.pages.update', $page), ['title' => 'x'])->assertForbidden();
        $this->actingAs($outsider)->deleteJson(route('api.v1.pages.destroy', $page))->assertForbidden();

        $this->actingAs($member)->get(route('pages.show', $page))->assertOk();
    }

    public function test_public_space_pages_are_shared_with_all_members(): void
    {
        $space = Space::factory()->create();
        $page = Page::factory()->create(['space_id' => $space->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('pages.show', $page))
            ->assertOk();
    }

    public function test_pages_can_only_be_created_in_accessible_spaces(): void
    {
        $user = User::factory()->create();
        $hidden = Space::factory()->private()->create();
        $open = Space::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.store'), ['space_id' => $hidden->id])
            ->assertJsonValidationErrors('space_id');

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.store'), ['space_id' => $open->id]);

        $this->assertDatabaseHas('pages', ['user_id' => $user->id, 'space_id' => $open->id]);
    }

    public function test_pages_default_to_the_personal_space(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.v1.boards.store'));

        $this->assertDatabaseHas('pages', ['user_id' => $user->id, 'space_id' => $user->personalSpace->id]);
    }

    public function test_pages_cannot_be_moved_into_a_hidden_space(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $hidden = Space::factory()->private()->create();

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['space_id' => $hidden->id])
            ->assertJsonValidationErrors('space_id');
    }

    public function test_only_admins_can_create_spaces(): void
    {
        $payload = ['name' => 'Team', 'visibility' => 'private'];

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.spaces.store'), $payload)
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('api.v1.spaces.store'), $payload)->assertCreated();

        $space = Space::where('name', 'Team')->firstOrFail();
        $this->assertSame(SpaceVisibility::Private, $space->visibility);
        $this->assertTrue($space->users->contains($admin));
    }

    public function test_only_admins_can_open_the_create_space_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('spaces.create'))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('spaces.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('spaces/create'));
    }

    public function test_new_spaces_default_to_open(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.v1.spaces.store'), ['name' => 'Team'])
            ->assertCreated();

        $this->assertSame(SpaceVisibility::Open, Space::where('name', 'Team')->firstOrFail()->visibility);
    }

    public function test_admin_can_change_space_visibility(): void
    {
        $space = Space::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.v1.spaces.update', $space), ['visibility' => 'private'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('api.v1.spaces.update', $space), ['visibility' => 'private']);

        $this->assertSame(SpaceVisibility::Private, $space->fresh()->visibility);
    }

    public function test_personal_space_has_no_settings_even_for_its_admin_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $space = $admin->personalSpace;
        $page = Page::factory()->for($admin)->create(['space_id' => $space->id]);
        $target = Space::factory()->create();

        $this->actingAs($admin);

        $this->get(route('spaces.edit', $space))->assertForbidden();
        $this->get(route('spaces.members', $space))->assertForbidden();
        $this->get(route('spaces.danger', $space))->assertForbidden();
        $this->patchJson(route('api.v1.spaces.update', $space), ['name' => 'Renamed', 'visibility' => 'open'])->assertForbidden();
        $this->postJson(route('api.v1.spaces.content.transfer', $space), ['target_space_id' => $target->id])->assertForbidden();
        $this->deleteJson(route('api.v1.spaces.content.destroy', $space), ['confirmation' => 'I accept the consequences'])->assertForbidden();
        $this->deleteJson(route('api.v1.spaces.destroy', $space))->assertForbidden();

        $this->assertSame(SpaceVisibility::Private, $space->fresh()->visibility);
        $this->assertSame($space->id, $page->fresh()->space_id);
        $this->assertFalse((new SpaceResource($space))->resolve(request())['can_update']);
    }

    public function test_admin_can_invite_and_remove_members(): void
    {
        $admin = User::factory()->admin()->create();
        $invitee = User::factory()->create();
        $space = Space::factory()->private()->create();
        $page = Page::factory()->for($admin)->create(['space_id' => $space->id]);

        $this->actingAs($invitee)->get(route('pages.show', $page))->assertForbidden();

        $this->actingAs($invitee)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => $invitee->email])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('api.v1.spaces.members.store', $space), ['email' => $invitee->email]);

        $this->actingAs($invitee)->get(route('pages.show', $page))->assertOk();

        $this->actingAs($admin)->deleteJson(route('api.v1.spaces.members.destroy', [$space, $invitee]));

        $this->actingAs($invitee)->get(route('pages.show', $page))->assertForbidden();
    }

    public function test_space_index_only_lists_visible_spaces_and_flags_abilities(): void
    {
        Space::factory()->private()->create(['name' => 'Secret']);
        Space::factory()->create(['name' => 'Shared']);

        $this->actingAs(User::factory()->create())
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('spaces/index')
                ->has('space_list', 1)
                ->where('space_list.0.name', 'Shared')
                ->where('space_list.0.can_manage', false));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('spaces.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('space_list', 2));
    }

    public function test_space_has_its_own_url_listing_only_its_pages(): void
    {
        $user = User::factory()->create();
        $space = $user->personalSpace;
        Page::factory()->for($user)->create();
        Page::factory()->for($user)->create(['space_id' => Space::factory()->create()->id]);

        $this->actingAs($user)
            ->get(route('spaces.show', $space))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('pages/index')
                ->where('space.id', $space->id)
                ->has('pages', 1));
    }

    public function test_private_space_url_is_forbidden_to_non_members(): void
    {
        $space = Space::factory()->private()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('spaces.show', $space))
            ->assertForbidden();
    }
}
