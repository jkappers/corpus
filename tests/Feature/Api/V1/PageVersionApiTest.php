<?php

namespace Tests\Feature\Api\V1;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageVersionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_versions_without_bodies_and_show_includes_the_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $version = PageVersion::factory()->for($page)->create(['body' => 'Old text']);

        $this->actingAs($user)->getJson(route('api.v1.pages.versions.index', $page))
            ->assertOk()
            ->assertJsonPath('data.0.id', $version->id)
            ->assertJsonMissingPath('data.0.body');

        $this->getJson(route('api.v1.pages.versions.show', [$page, $version]))
            ->assertOk()
            ->assertJsonPath('data.body', 'Old text');
    }

    public function test_show_returns_404_for_a_version_of_another_page(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $other = PageVersion::factory()->for(Page::factory()->for($user))->create();

        $this->actingAs($user)->getJson(route('api.v1.pages.versions.show', [$page, $other]))->assertNotFound();
    }

    public function test_restore_snapshots_current_state_then_overwrites_the_page(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'Current']);
        $version = PageVersion::factory()->for($page)->create(['title' => 'Old', 'body' => 'Old text']);

        $this->actingAs($user)->postJson(route('api.v1.pages.versions.restore', [$page, $version]))
            ->assertOk()
            ->assertJsonPath('data.body', 'Old text');

        $this->assertSame('Old', $page->refresh()->title);
        $this->assertDatabaseHas('page_versions', ['page_id' => $page->id, 'reason' => 'pre-restore', 'body' => 'Current']);
    }

    public function test_versions_of_private_spaces_are_forbidden_to_outsiders(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.v1.pages.versions.index', $page))
            ->assertForbidden();
    }

    public function test_board_versions_are_listed_and_restored_under_boards(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create(['title' => 'Current']);
        $version = PageVersion::factory()->for($board)->create(['title' => 'Old', 'body' => null, 'data' => Page::emptyBoard()]);

        $this->actingAs($user)->getJson(route('api.v1.boards.versions.index', $board))
            ->assertOk()
            ->assertJsonPath('data.0.id', $version->id);

        $this->postJson(route('api.v1.boards.versions.restore', [$board, $version]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Old');

        $this->assertSame('Old', $board->refresh()->title);
    }
}
