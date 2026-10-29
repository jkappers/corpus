<?php

namespace Tests\Feature;

use App\Enums\SpaceRole;
use App\Models\Attachment;
use App\Models\Notation;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpaceAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Space, User, User, User, User}
     */
    private function privateSpace(): array
    {
        [$manager, $editor, $viewer, $outsider] = User::factory()->count(4)->create()->all();
        $space = Space::factory()->private()->create();
        $space->users()->attach($manager, ['role' => SpaceRole::Manager->value]);
        $space->users()->attach($editor, ['role' => SpaceRole::Editor->value]);
        $space->users()->attach($viewer, ['role' => SpaceRole::Viewer->value]);

        return [$space, $manager, $editor, $viewer, $outsider];
    }

    public function test_private_space_content_is_limited_to_members(): void
    {
        Storage::fake('local');
        [$space, $manager, $editor, $viewer, $outsider] = $this->privateSpace();
        $page = Page::factory()->for($manager)->create(['space_id' => $space->id]);
        $board = Page::factory()->board()->for($manager)->create(['space_id' => $space->id]);
        $attachment = Attachment::factory()->for($page)->create();

        foreach ([$manager, $editor, $viewer] as $member) {
            $this->actingAs($member)->get(route('pages.show', $page))->assertOk();
            $this->actingAs($member)->get(route('boards.show', $board))->assertOk();
        }

        $this->actingAs($outsider)->get(route('pages.show', $page))->assertForbidden();
        $this->actingAs($outsider)->get(route('boards.show', $board))->assertForbidden();
        $this->actingAs($outsider)->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs($outsider)->get(route('spaces.show', $space))->assertForbidden();
    }

    public function test_visible_space_is_readable_but_not_editable_by_non_members(): void
    {
        $space = Space::factory()->visible()->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('pages.show', $page))->assertOk();
        $this->actingAs($outsider)
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'Nope'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->deleteJson(route('api.v1.pages.destroy', $page))
            ->assertForbidden();
    }

    public function test_open_space_is_editable_by_anyone(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'Edited'])
            ->assertOk();
    }

    public function test_viewers_cannot_edit_pages_or_upload_files(): void
    {
        [$space, $manager, $editor, $viewer] = $this->privateSpace();
        $page = Page::factory()->for($manager)->create(['space_id' => $space->id]);

        $this->actingAs($viewer)
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'Nope'])
            ->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('api.v1.pages.destroy', $page))->assertForbidden();
        $this->actingAs($viewer)
            ->postJson(route('api.v1.pages.store'), ['space_id' => $space->id])
            ->assertJsonValidationErrors('space_id');

        $this->actingAs($editor)
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'Yes'])
            ->assertOk();
    }

    public function test_page_cannot_be_moved_into_a_space_the_user_cannot_edit(): void
    {
        [$space, $manager, $editor, $viewer] = $this->privateSpace();
        $mine = Page::factory()->for($viewer)->create(['space_id' => Space::factory()->create()->id]);

        $this->actingAs($viewer)
            ->patchJson(route('api.v1.pages.update', $mine), ['space_id' => $space->id])
            ->assertJsonValidationErrors('space_id');

        $this->actingAs($editor)
            ->patchJson(route('api.v1.pages.update', $mine), ['space_id' => $space->id])
            ->assertOk();
    }

    public function test_removed_member_cannot_touch_notations_or_count_them(): void
    {
        [$space, $manager, $editor] = $this->privateSpace();
        $page = Page::factory()->for($manager)->create(['space_id' => $space->id]);
        $notation = Notation::factory()->for($page)->create([
            'author_id' => $manager->id,
            'assignee_id' => $editor->id,
        ]);

        $space->users()->detach($editor);

        $this->actingAs($editor)->putJson(route('api.v1.notations.complete', $notation))->assertForbidden();
        $this->actingAs($editor)->deleteJson(route('api.v1.notations.destroy', $notation))->assertForbidden();
        $this->actingAs($editor)
            ->get(route('inbox.index'))
            ->assertInertia(fn ($inertia) => $inertia->where('inboxCount', 0)->has('notations', 0));
    }

    public function test_managers_can_update_settings_but_editors_cannot(): void
    {
        [$space, $manager, $editor] = $this->privateSpace();

        $this->actingAs($editor)
            ->patchJson(route('api.v1.spaces.update', $space), ['name' => 'Renamed'])
            ->assertForbidden();
        $this->actingAs($editor)->get(route('spaces.edit', $space))->assertForbidden();
        $this->actingAs($editor)->get(route('spaces.members', $space))->assertForbidden();
        $this->actingAs($editor)->get(route('spaces.danger', $space))->assertForbidden();

        $this->actingAs($manager)
            ->patchJson(route('api.v1.spaces.update', $space), ['name' => 'Renamed', 'visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('data.can_update_visibility', false);
        $this->actingAs($manager)->get(route('spaces.edit', $space))->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('spaces/settings/general'));
        $this->actingAs($manager)->get(route('spaces.members', $space))->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('spaces/settings/members')->has('space.members'));
        $this->actingAs($manager)->get(route('spaces.danger', $space))->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->component('spaces/settings/danger')->has('transfer_targets'));

        $space->refresh();
        $this->assertSame('Renamed', $space->name);
    }

    public function test_managers_cannot_change_visibility(): void
    {
        [$space, $manager] = $this->privateSpace();

        $this->actingAs($manager)
            ->patchJson(route('api.v1.spaces.update', $space), ['visibility' => 'visible'])
            ->assertForbidden();

        $this->assertSame('private', $space->fresh()->visibility->value);
    }

    public function test_inertia_shares_role_and_abilities(): void
    {
        [$space, , , $viewer] = $this->privateSpace();

        $this->actingAs($viewer)
            ->get(route('spaces.show', $space))
            ->assertInertia(fn ($inertia) => $inertia
                ->where('space.role', 'viewer')
                ->where('space.can_edit', false)
                ->where('space.can_manage', false));
    }

    public function test_shared_spaces_put_own_personal_space_first_and_hide_others(): void
    {
        $mine = User::factory()->create();
        User::factory()->create();
        $mySpace = $mine->personalSpace;
        Space::factory()->create(['name' => 'A shared space']);

        $this->actingAs($mine)
            ->get(route('home'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('spaces', 2)
                ->where('spaces.0.id', $mySpace->id)
                ->where('spaces.0.is_personal', true)
                ->where('spaces.0.is_mine', true)
                ->where('spaces.0.owner_name', null)
                ->where('spaces.1.is_personal', false));
    }

    public function test_admin_sees_other_users_personal_spaces_with_owner_name(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['name' => 'Pat Member']);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('spaces', 2)
                ->where('spaces.0.is_mine', true)
                ->where('spaces', fn ($spaces) => collect($spaces)->contains(fn ($item) => $item['owner_name'] === 'Pat Member' && ! $item['is_mine'])));
    }

    public function test_personal_space_pages_are_private_to_owner_and_admins(): void
    {
        [$owner, $other] = User::factory()->count(2)->create()->all();
        $admin = User::factory()->admin()->create();
        $space = $owner->personalSpace;
        $page = Page::factory()->for($owner)->create(['space_id' => $space->id]);

        $this->actingAs($owner)->get(route('pages.show', $page))->assertOk();
        $this->actingAs($admin)->get(route('pages.show', $page))->assertOk();
        $this->actingAs($other)->get(route('pages.show', $page))->assertForbidden();
    }
}
