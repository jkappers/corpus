<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SpaceRole;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_for_guests(): void
    {
        $this->getJson(route('api.v1.pages.index'))->assertUnauthorized();
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_me_returns_the_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_index_lists_only_pages_in_visible_spaces_with_excerpt_and_no_body(): void
    {
        $user = User::factory()->create();
        $visible = Page::factory()->for($user)->create(['title' => 'Visible', 'body' => str_repeat('a', 300)]);
        $hidden = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $response = $this->actingAs($user)->getJson(route('api.v1.pages.index'))->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonMissingPath('data.0.body');
        $this->assertSame(160, strlen($response->json('data.0.excerpt')));
        $this->assertNotContains($hidden->id, array_column($response->json('data'), 'id'));
    }

    public function test_pages_and_boards_are_listed_separately_and_filtered_by_search_term(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Roadmap']);
        Page::factory()->for($user)->create(['title' => 'Other']);
        Page::factory()->board()->for($user)->create(['title' => 'Roadmap board']);

        $this->actingAs($user)->getJson(route('api.v1.pages.index', ['q' => 'Roadmap']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Roadmap');

        $this->getJson(route('api.v1.boards.index', ['q' => 'Roadmap']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Roadmap board');
    }

    public function test_page_and_board_endpoints_do_not_resolve_each_others_ids(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $board = Page::factory()->board()->for($user)->create(['title' => 'Kept']);
        $trashedBoard = Page::factory()->board()->for($user)->create(['deleted_at' => now()]);

        $this->actingAs($user);
        $this->getJson(route('api.v1.pages.show', $board->id))->assertNotFound();
        $this->patchJson(route('api.v1.pages.update', $board->id), ['title' => 'x'])->assertNotFound();
        $this->deleteJson(route('api.v1.pages.destroy', $board->id))->assertNotFound();
        $this->getJson(route('api.v1.pages.versions.index', $board->id))->assertNotFound();
        $this->postJson(route('api.v1.pages.restore', $trashedBoard->id))->assertNotFound();
        $this->getJson(route('api.v1.boards.show', $page->id))->assertNotFound();

        $this->assertSame('Kept', $board->refresh()->title);
        $this->assertNotSoftDeleted($board);
        $this->assertSoftDeleted($trashedBoard);
    }

    public function test_update_can_append_to_the_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'First']);

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['body' => 'Second', 'mode' => 'append'])
            ->assertOk();

        $this->assertSame("First\n\nSecond", $page->fresh()->body);
    }

    public function test_index_sorts_by_the_requested_column(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'banana']);
        Page::factory()->for($user)->create(['title' => 'Apple']);

        $this->actingAs($user)
            ->getJson(route('api.v1.pages.index', ['sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Apple')
            ->assertJsonPath('data.1.title', 'banana');

        $this->getJson(route('api.v1.pages.index', ['sort' => 'bogus']))->assertJsonValidationErrors('sort');
    }

    public function test_index_can_list_trashed_pages(): void
    {
        $user = User::factory()->create();
        $trashed = Page::factory()->for($user)->create();
        $trashed->delete();
        Page::factory()->for($user)->create();

        $this->actingAs($user)->getJson(route('api.v1.pages.index', ['trashed' => 'only']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trashed->id);
    }

    public function test_store_creates_an_untitled_page_in_the_personal_space_and_returns_201(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.store'))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Untitled')
            ->assertJsonMissingPath('data.board');

        $this->assertSame($user->personalSpace->id, Page::sole()->space_id);
    }

    public function test_store_accepts_an_initial_title_and_body(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.store'), ['title' => 'Plan', 'body' => '# Goals'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Plan')
            ->assertJsonPath('data.body', '# Goals');
    }

    public function test_store_rejects_a_body_for_a_board(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.boards.store'), ['body' => '# Goals'])
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, Page::count());
    }

    public function test_store_rejects_a_space_the_user_cannot_edit(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$viewer], SpaceRole::Viewer)->create();

        $this->actingAs($viewer)
            ->postJson(route('api.v1.pages.store'), ['space_id' => $space->id])
            ->assertJsonValidationErrors('space_id');
    }

    public function test_show_returns_body_and_edit_ability(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'Hello']);

        $this->actingAs($user)->getJson(route('api.v1.pages.show', $page))
            ->assertOk()
            ->assertJsonPath('data.body', 'Hello')
            ->assertJsonPath('meta.can_edit', true);
    }

    public function test_show_forbids_non_members_of_a_private_space(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.pages.show', $page))
            ->assertForbidden();
    }

    public function test_update_saves_changes_and_returns_the_new_updated_at(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->patchJson(route('api.v1.pages.update', $page), ['title' => 'New', 'body' => 'Text'])
            ->assertOk()
            ->assertJsonPath('data.id', $page->id)
            ->assertJsonStructure(['data' => ['updated_at']]);

        $this->assertSame('New', $page->refresh()->title);
        $this->assertSame('Text', $page->body);
    }

    public function test_update_returns_409_when_base_updated_at_is_stale(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $stale = $page->updated_at->subMinute()->toIso8601String();

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['body' => 'Overwrite', 'base_updated_at' => $stale])
            ->assertConflict();

        $this->assertNotSame('Overwrite', $page->refresh()->body);
    }

    public function test_update_accepts_a_matching_base_updated_at(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), [
                'body' => 'Fresh',
                'base_updated_at' => $page->updated_at->toIso8601String(),
            ])
            ->assertOk();

        $this->assertSame('Fresh', $page->refresh()->body);
    }

    public function test_update_saves_board_content_sent_as_board(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create();
        $content = ['nodes' => [], 'edges' => [], 'viewport' => ['x' => 1, 'y' => 2, 'zoom' => 1]];

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['board' => $content])->assertOk();

        $this->assertEquals($content, $board->refresh()->data);
        $this->getJson(route('api.v1.boards.show', $board))
            ->assertJsonPath('data.board.viewport.x', 1)
            ->assertJsonMissingPath('data.body');
    }

    public function test_update_forbids_viewers(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$viewer], SpaceRole::Viewer)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);

        $this->actingAs($viewer)->patchJson(route('api.v1.pages.update', $page), ['title' => 'x'])
            ->assertForbidden();
    }

    public function test_destroy_restore_and_force_delete_round_trip(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson(route('api.v1.pages.destroy', $page))->assertNoContent();
        $this->assertSoftDeleted($page);

        $this->postJson(route('api.v1.pages.restore', $page->id))
            ->assertOk()
            ->assertJsonPath('data.id', $page->id);
        $this->assertNotSoftDeleted($page);

        $page->delete();
        $this->deleteJson(route('api.v1.pages.force-destroy', $page->id))->assertNoContent();
        $this->assertModelMissing($page);
    }

    public function test_restore_forbids_viewers(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$viewer], SpaceRole::Viewer)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        $page->delete();

        $this->actingAs($viewer)->postJson(route('api.v1.pages.restore', $page->id))->assertForbidden();
    }
}
