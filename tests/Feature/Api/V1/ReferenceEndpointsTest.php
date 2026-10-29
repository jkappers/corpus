<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SpaceRole;
use App\Models\Attachment;
use App\Models\Notation;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferenceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_serves_enums_limits_and_defaults(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.v1.meta'))
            ->assertOk()
            ->assertJsonPath('data.untitledTitle', Page::UNTITLED)
            ->assertJsonPath('data.trashRetentionDays', Page::TRASH_RETENTION_DAYS)
            ->assertJsonPath('data.enums.spaceVisibility.0.value', 'open')
            ->assertJsonStructure(['data' => ['attachments' => ['accept'], 'attachmentKinds', 'notationBodyMax']]);
    }

    public function test_space_members_are_listed_for_managers_only(): void
    {
        $manager = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->withMembers([$manager], SpaceRole::Manager)->create();
        $space->users()->attach($viewer, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($manager)->getJson(route('api.v1.spaces.members.index', $space))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $viewer->id, 'role' => 'viewer']);

        $this->actingAs($viewer)->getJson(route('api.v1.spaces.members.index', $space))->assertForbidden();
    }

    public function test_assignable_users_respect_private_spaces(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$member], SpaceRole::Editor)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);

        $ids = $this->actingAs($member)->getJson(route('api.v1.pages.assignable-users', $page))
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($member->id, $ids);
        $this->assertNotContains($outsider->id, $ids);
    }

    public function test_notation_count_reports_open_inbox_items(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Notation::factory()->for($page)->create(['author_id' => $user->id]);
        Notation::factory()->for($page)->completed()->create(['author_id' => $user->id]);

        $this->actingAs($user)->getJson(route('api.v1.notations.count'))->assertJsonPath('data.open', 1);
    }

    public function test_space_index_reports_whether_the_user_can_create(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('api.v1.spaces.index'))
            ->assertOk()
            ->assertJsonStructure(['meta' => ['can_create']]);
    }

    public function test_attachment_metadata_and_content(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $attachment = Attachment::factory()->for($page)->create(['original_name' => 'photo.png']);
        Storage::disk($attachment->disk)->put($attachment->path, 'binary');

        $this->actingAs($user)->getJson(route('api.v1.attachments.show', $attachment))
            ->assertOk()
            ->assertJsonPath('data.name', 'photo.png');

        $this->get(route('api.v1.attachments.content', $attachment))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $hidden = Attachment::factory()->for(Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]))->create();
        $this->getJson(route('api.v1.attachments.show', $hidden))->assertForbidden();
    }
}
