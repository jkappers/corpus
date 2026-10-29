<?php

namespace Tests\Feature\Contract;

use App\Mcp\Servers\CorpusServer;
use App\Mcp\Tools\ListNotationsTool;
use App\Mcp\Tools\ListSpacesTool;
use App\Mcp\Tools\ListVersionsTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Models\Notation;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MCP tools and the REST API share Queries, Actions and Resources. These tests fail if one
 * path starts returning different records or enforcing different rules for the same input.
 */
class McpApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_search_and_page_index_return_the_same_pages(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Roadmap alpha']);
        Page::factory()->for($user)->create(['title' => 'Roadmap beta']);
        Page::factory()->create(['title' => 'Roadmap hidden', 'space_id' => Space::factory()->private()->create()->id]);

        $titles = $this->actingAs($user)
            ->getJson(route('api.v1.pages.index', ['q' => 'Roadmap']))
            ->assertOk()
            ->json('data.*.title');

        $tool = CorpusServer::actingAs($user)->tool(SearchTool::class, ['query' => 'Roadmap'])->assertOk();

        $this->assertEqualsCanonicalizing(['Roadmap alpha', 'Roadmap beta'], $titles);
        $tool->assertSee($titles)->assertDontSee('Roadmap hidden');
    }

    public function test_trashed_search_matches_the_trashed_index(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Live page']);
        Page::factory()->for($user)->create(['title' => 'Gone page', 'deleted_at' => now()]);

        $titles = $this->actingAs($user)
            ->getJson(route('api.v1.pages.index', ['trashed' => 'only']))
            ->json('data.*.title');

        $this->assertSame(['Gone page'], $titles);
        CorpusServer::actingAs($user)->tool(SearchTool::class, ['trashed' => true])
            ->assertSee('Gone page')
            ->assertDontSee('Live page');
    }

    public function test_space_listing_matches(): void
    {
        $user = User::factory()->create();
        Space::factory()->create(['name' => 'Shared one', 'visibility' => 'open']);
        Space::factory()->private()->create(['name' => 'Secret one']);

        $names = $this->actingAs($user)->getJson(route('api.v1.spaces.index'))->json('data.*.name');

        $this->assertContains('Shared one', $names);
        $this->assertNotContains('Secret one', $names);
        CorpusServer::actingAs($user)->tool(ListSpacesTool::class, [])
            ->assertSee('Shared one')
            ->assertDontSee('Secret one');
    }

    public function test_inbox_matches(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Notation::factory()->for($page)->create(['author_id' => $user->id, 'body' => 'mine open']);
        Notation::factory()->for($page)->completed()->create(['author_id' => $user->id, 'body' => 'mine done']);

        $this->actingAs($user)->getJson(route('api.v1.notations.index'))->assertJsonPath('data.0.body', 'mine open')->assertJsonCount(1, 'data');

        CorpusServer::actingAs($user)->tool(ListNotationsTool::class, [])
            ->assertSee('mine open')
            ->assertDontSee('mine done');
    }

    public function test_version_listing_matches(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        PageVersion::factory()->for($page)->create(['title' => 'Version one']);

        $this->actingAs($user)->getJson(route('api.v1.pages.versions.index', $page))->assertJsonPath('data.0.title', 'Version one');

        CorpusServer::actingAs($user)->tool(ListVersionsTool::class, ['id' => $page->id])->assertSee('Version one');
    }

    public function test_stale_writes_are_refused_by_both(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $stale = $page->updated_at->copy()->subMinute()->toIso8601String();

        $this->actingAs($user)
            ->patchJson(route('api.v1.pages.update', $page), ['title' => 'x', 'base_updated_at' => $stale])
            ->assertStatus(409);

        CorpusServer::actingAs($user)
            ->tool(UpdatePageTool::class, ['page_id' => $page->id, 'title' => 'x', 'base_updated_at' => $stale])
            ->assertHasErrors(['changed since base_updated_at']);
    }
}
