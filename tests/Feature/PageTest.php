<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\PageType;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_index_includes_a_truncated_excerpt_of_the_body(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Long', 'body' => str_repeat('a', 500)]);

        $this->actingAs($user)->get(route('home'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('pages.0.excerpt', str_repeat('a', 160)));
    }

    public function test_home_lists_recent_pages_and_boards_the_user_can_see(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Roadmap', 'updated_at' => now()->subDay()]);
        Page::factory()->for($user)->board()->create(['title' => 'Sketch']);
        Page::factory()->create(['title' => 'Roadmap of someone else']);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('pages/index')
                ->where('heading', 'Recent')
                ->where('filters', null)
                ->has('pages', 2)
                ->where('pages.0.title', 'Sketch')
                ->where('pages.1.title', 'Roadmap'));
    }

    public function test_space_can_be_narrowed_to_one_type(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Roadmap']);
        Page::factory()->for($user)->board()->create(['title' => 'Sketch']);

        $this->actingAs($user)->get(route('spaces.show', [$user->personalSpace, 'type' => 'board']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('type', 'board')
                ->has('pages', 1)
                ->where('pages.0.title', 'Sketch'));

        $this->actingAs($user)->get(route('spaces.show', [$user->personalSpace, 'type' => 'nonsense']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('type', null)
                ->has('pages', 2));
    }

    public function test_user_can_create_a_doc_and_a_board(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.store'))->assertCreated();
        $this->actingAs($user)->postJson(route('api.v1.boards.store'))->assertCreated();

        $page = $user->pages()->where('type', PageType::Page)->first();
        $board = $user->pages()->where('type', PageType::Board)->first();

        $this->assertSame('', $page->body);
        $this->assertNull($page->data);
        $this->assertNull($board->body);
        $this->assertSame([], $board->data['nodes']);
    }

    public function test_pages_and_boards_render_on_their_own_screens_and_old_board_links_redirect(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $board = Page::factory()->for($user)->board()->create();

        $this->actingAs($user)->get(route('pages.show', $page))
            ->assertInertia(fn (Assert $inertia) => $inertia->component('pages/page'));
        $this->get(route('boards.show', $board))
            ->assertInertia(fn (Assert $inertia) => $inertia->component('pages/board'));

        $this->get(route('pages.show', $board->id))->assertRedirect(route('boards.show', $board));
        $this->get(route('pages.show', 999999))->assertNotFound();
        $this->get(route('boards.show', $page->id))->assertNotFound();
    }

    public function test_other_users_get_403(): void
    {
        $page = Page::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('pages.show', $page))->assertForbidden();
        $this->actingAs($other)->patchJson(route('api.v1.pages.update', $page), ['title' => 'x'])->assertForbidden();
        $this->actingAs($other)->deleteJson(route('api.v1.pages.destroy', $page))->assertForbidden();
    }

    public function test_update_persists_doc_title_and_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'Plan', 'body' => "# Plan\n\n- one"])
            ->assertOk();

        $page->refresh();
        $this->assertSame('Plan', $page->title);
        $this->assertSame("# Plan\n\n- one", $page->body);
    }

    public function test_update_persists_image_layout_fragment_in_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $body = '![shot](/attachments/01ABC#a=right&w=50)';

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['body' => $body])
            ->assertOk();

        $this->assertSame($body, $page->refresh()->body);
    }

    public function test_update_persists_page_link_in_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $target = Page::factory()->for($user)->create();
        $body = "See [Target]({$this->pageLinkPath($target)}) for details.";

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['body' => $body])
            ->assertOk();

        $this->assertSame($body, $page->refresh()->body);
    }

    private function pageLinkPath(Page $page): string
    {
        return route('pages.show', $page, absolute: false);
    }

    public function test_blank_title_falls_back_to_untitled(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['title' => 'Plan']);

        $this->actingAs($user)->patchJson(route('api.v1.pages.update', $page), ['title' => ''])->assertOk();

        $this->assertSame('Untitled', $page->refresh()->title);
    }

    public function test_update_persists_board_data(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->for($user)->board()->create();

        $data = [
            'nodes' => [['id' => 'a', 'type' => 'rect', 'position' => ['x' => 1, 'y' => 2], 'data' => ['label' => 'Hi']]],
            'edges' => [['id' => 'e1', 'source' => 'a', 'target' => 'a']],
            'viewport' => ['x' => 10, 'y' => 20, 'zoom' => 1.5],
        ];

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['data' => $data])->assertOk();

        $this->assertSame('Hi', $board->refresh()->data['nodes'][0]['data']['label']);
        $this->assertEquals(1.5, $board->data['viewport']['zoom']);
    }

    public function test_update_accepts_an_empty_board(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->for($user)->board()->create();

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['data' => Page::emptyBoard()])
            ->assertOk();
    }

    public function test_title_only_update_leaves_board_data_untouched(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->for($user)->board()->create();

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['title' => 'Renamed'])
            ->assertOk();

        $this->assertSame([], $board->refresh()->data['nodes']);
    }

    public function test_update_rejects_fields_for_the_wrong_type(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $board = Page::factory()->for($user)->board()->create();

        $this->actingAs($user)->patchJson(route('api.v1.pages.update', $page), ['data' => Page::emptyBoard()])
            ->assertJsonValidationErrors('data');
        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['body' => 'x'])
            ->assertJsonValidationErrors('body');
    }

    public function test_update_rejects_malformed_or_oversized_board_data(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->for($user)->board()->create();

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), ['data' => ['nodes' => []]])
            ->assertJsonValidationErrors(['data.edges', 'data.viewport']);

        $this->actingAs($user)->patchJson(route('api.v1.boards.update', $board), [
            'data' => ['nodes' => array_fill(0, 2001, ['id' => 'n']), 'edges' => [], 'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1]],
        ])->assertJsonValidationErrors('data.nodes');
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson(route('api.v1.pages.destroy', $page))
            ->assertNoContent();

        $this->assertSoftDeleted($page);
    }

    public function test_trash_lists_only_trashed_pages(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Live']);
        Page::factory()->for($user)->create(['title' => 'Old plan', 'deleted_at' => now()]);
        Page::factory()->for($user)->create(['title' => 'Old notes', 'deleted_at' => now()]);

        $this->actingAs($user)->get(route('home'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('pages', 1)->where('pages.0.title', 'Live'));

        $this->actingAs($user)->get(route('pages.trash'))
            ->assertInertia(fn (Assert $inertia) => $inertia->component('pages/trash')->has('pages', 2));
    }

    public function test_trash_reports_days_left_before_purge(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['deleted_at' => now()->subDays(10)->subHour()]);

        $this->actingAs($user)->get(route('pages.trash'))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('pages.0.days_left', Page::TRASH_RETENTION_DAYS - 10));
    }

    public function test_listing_is_sorted_by_the_requested_column(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'banana']);
        Page::factory()->for($user)->create(['title' => 'Apple']);
        Page::factory()->for($user)->create(['title' => 'cherry']);

        $this->actingAs($user)->get(route('spaces.show', [$user->personalSpace, 'sort' => 'title', 'direction' => 'asc']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('pages.0.title', 'Apple')
                ->where('pages.2.title', 'cherry')
                ->where('filters.sort', 'title')
                ->where('filters.direction', 'asc'));

        $this->actingAs($user)->get(route('spaces.show', [$user->personalSpace, 'sort' => 'nonsense']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('filters.sort', 'updated')
                ->where('filters.direction', 'desc'));
    }

    public function test_trash_hides_pages_in_inaccessible_spaces(): void
    {
        $user = User::factory()->create();
        Page::factory()->create(['deleted_at' => now()]);

        $this->actingAs($user)->get(route('pages.trash'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('pages', 0));
    }

    public function test_restore_and_force_delete(): void
    {
        $user = User::factory()->create();
        $restored = Page::factory()->for($user)->create(['deleted_at' => now()]);
        $gone = Page::factory()->for($user)->create(['deleted_at' => now()]);

        $this->actingAs($user)->postJson(route('api.v1.pages.restore', $restored->id))
            ->assertOk();
        $this->actingAs($user)->deleteJson(route('api.v1.pages.force-destroy', $gone->id))
            ->assertNoContent();

        $this->assertNotSoftDeleted($restored);
        $this->assertModelMissing($gone);
    }

    public function test_other_users_cannot_restore_or_force_delete(): void
    {
        $page = Page::factory()->create(['deleted_at' => now()]);
        $other = User::factory()->create();

        $this->actingAs($other)->postJson(route('api.v1.pages.restore', $page->id))->assertForbidden();
        $this->actingAs($other)->deleteJson(route('api.v1.pages.force-destroy', $page->id))->assertForbidden();
    }

    public function test_prune_removes_pages_trashed_over_30_days_ago(): void
    {
        $old = Page::factory()->create(['deleted_at' => now()->subDays(31)]);
        $recent = Page::factory()->create(['deleted_at' => now()->subDays(29)]);
        $live = Page::factory()->create();

        $this->artisan('model:prune', ['--model' => [Page::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($live);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditEvent::PageForceDeleted->value,
            'subject_id' => $old->id,
            'actor_id' => null,
        ]);
    }
}
