<?php

namespace Tests\Feature\Mcp;

use App\Enums\PageType;
use App\Enums\SpaceRole;
use App\Mcp\Servers\CorpusServer;
use App\Mcp\Tools\AddNotationTool;
use App\Mcp\Tools\CompleteNotationTool;
use App\Mcp\Tools\CreatePageTool;
use App\Mcp\Tools\FetchTool;
use App\Mcp\Tools\ListNotationsTool;
use App\Mcp\Tools\ListSpacesTool;
use App\Mcp\Tools\ListVersionsTool;
use App\Mcp\Tools\RestoreVersionTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\TrashTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Models\Notation;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    private function privatePage(User $viewer): Page
    {
        $space = Space::factory()->private()->withMembers([$viewer], SpaceRole::Viewer)->create();

        return Page::factory()->create(['space_id' => $space->id, 'title' => 'Secret plan']);
    }

    public function test_search_pages_returns_visible_pages_only(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Roadmap']);
        Page::factory()->create(['title' => 'Roadmap hidden', 'space_id' => Space::factory()->private()->create()->id]);

        CorpusServer::actingAs($user)->tool(SearchTool::class, ['query' => 'Roadmap'])
            ->assertOk()
            ->assertSee('Roadmap')
            ->assertDontSee('Roadmap hidden');
    }

    public function test_read_page_returns_the_body_and_updated_at(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'Hello corpus']);

        CorpusServer::actingAs($user)->tool(FetchTool::class, ['id' => $page->id])
            ->assertOk()
            ->assertSee(['Hello corpus', 'updated_at']);
    }

    public function test_read_page_reports_a_permission_error_for_a_private_space(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        CorpusServer::actingAs(User::factory()->create())->tool(FetchTool::class, ['id' => $page->id])
            ->assertHasErrors(['You do not have permission to do that.']);
    }

    public function test_read_page_reports_not_found(): void
    {
        CorpusServer::actingAs(User::factory()->create())->tool(FetchTool::class, ['id' => 9999])
            ->assertHasErrors(['Not found.']);
    }

    public function test_create_page_makes_a_page_with_title_and_body_in_the_personal_space(): void
    {
        $user = User::factory()->create();

        CorpusServer::actingAs($user)->tool(CreatePageTool::class, ['title' => 'Notes', 'body' => '# Hi'])
            ->assertOk();

        $page = Page::sole();
        $this->assertSame('Notes', $page->title);
        $this->assertSame('# Hi', $page->body);
        $this->assertSame($user->personalSpace->id, $page->space_id);
    }

    public function test_create_page_is_refused_in_a_space_the_user_cannot_edit(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$viewer], SpaceRole::Viewer)->create();

        CorpusServer::actingAs($viewer)->tool(CreatePageTool::class, ['space_id' => $space->id])
            ->assertHasErrors(['You do not have permission to do that.']);

        $this->assertSame(0, Page::count());
    }

    public function test_update_page_replaces_and_appends_the_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'One']);

        CorpusServer::actingAs($user)->tool(UpdatePageTool::class, ['page_id' => $page->id, 'body' => 'Two', 'mode' => 'append'])->assertOk();
        $this->assertSame("One\n\nTwo", $page->refresh()->body);

        CorpusServer::actingAs($user)->tool(UpdatePageTool::class, ['page_id' => $page->id, 'title' => 'Renamed', 'body' => 'Fresh'])->assertOk();
        $this->assertSame('Fresh', $page->refresh()->body);
        $this->assertSame('Renamed', $page->title);
    }

    public function test_update_page_rejects_a_stale_base_updated_at_and_keeps_the_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'Original']);

        CorpusServer::actingAs($user)->tool(UpdatePageTool::class, [
            'page_id' => $page->id,
            'body' => 'Overwrite',
            'base_updated_at' => $page->updated_at->subMinute()->toIso8601String(),
        ])->assertHasErrors();

        $this->assertSame('Original', $page->refresh()->body);
    }

    public function test_update_page_treats_a_board_as_not_found(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create();
        $title = $board->title;

        CorpusServer::actingAs($user)->tool(UpdatePageTool::class, ['page_id' => $board->id, 'title' => 'x'])
            ->assertHasErrors(['Not found.']);

        $this->assertSame($title, $board->refresh()->title);
    }

    public function test_search_and_fetch_cover_boards_and_report_the_type(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create(['title' => 'Roadmap board']);

        CorpusServer::actingAs($user)->tool(SearchTool::class, ['query' => 'Roadmap', 'type' => 'board'])
            ->assertOk()
            ->assertSee(['"type":"board"', 'Roadmap board']);

        CorpusServer::actingAs($user)->tool(FetchTool::class, ['id' => $board->id])
            ->assertOk()
            ->assertSee(['"type":"board"', '"nodes"'])
            ->assertDontSee('"body"');
    }

    public function test_page_only_tools_treat_a_board_id_as_not_found(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create();

        CorpusServer::actingAs($user)->tool(AddNotationTool::class, [
            'page_id' => $board->id, 'quote' => 'q', 'from' => 0, 'to' => 1, 'body' => 'b',
        ])->assertHasErrors(['Not found.']);
        CorpusServer::actingAs($user)->tool(ListNotationsTool::class, ['page_id' => $board->id])
            ->assertHasErrors(['Not found.']);

        $this->assertSame(0, Notation::count());
    }

    public function test_create_page_only_makes_pages(): void
    {
        $user = User::factory()->create();

        CorpusServer::actingAs($user)->tool(CreatePageTool::class, ['type' => 'board', 'title' => 'Plan'])->assertOk();

        $this->assertSame(PageType::Page, Page::sole()->type);
    }

    public function test_update_page_is_forbidden_for_viewers(): void
    {
        $viewer = User::factory()->create();
        $page = $this->privatePage($viewer);

        CorpusServer::actingAs($viewer)->tool(UpdatePageTool::class, ['page_id' => $page->id, 'title' => 'Hacked'])
            ->assertHasErrors(['You do not have permission to do that.']);

        $this->assertSame('Secret plan', $page->refresh()->title);
    }

    public function test_trash_page_soft_deletes_and_is_forbidden_for_viewers(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $viewer = User::factory()->create();
        $locked = $this->privatePage($viewer);

        CorpusServer::actingAs($user)->tool(TrashTool::class, ['id' => $page->id])->assertOk();
        $this->assertSoftDeleted($page);

        CorpusServer::actingAs($viewer)->tool(TrashTool::class, ['id' => $locked->id])
            ->assertHasErrors(['You do not have permission to do that.']);
        $this->assertNotSoftDeleted($locked);
    }

    public function test_list_spaces_shows_only_visible_spaces(): void
    {
        $user = User::factory()->create();
        Space::factory()->private()->create(['name' => 'Hidden space']);

        CorpusServer::actingAs($user)->tool(ListSpacesTool::class)
            ->assertOk()
            ->assertSee($user->personalSpace->name)
            ->assertDontSee('Hidden space');
    }

    public function test_notation_tools_add_list_and_complete(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        CorpusServer::actingAs($user)->tool(AddNotationTool::class, [
            'page_id' => $page->id,
            'quote' => 'hello',
            'from' => 1,
            'to' => 6,
            'body' => 'Check this',
        ])->assertOk();

        $notation = Notation::sole();
        $this->assertSame($user->id, $notation->assignee_id);

        CorpusServer::actingAs($user)->tool(ListNotationsTool::class, ['page_id' => $page->id])->assertSee('Check this');
        CorpusServer::actingAs($user)->tool(ListNotationsTool::class)->assertSee('Check this');

        CorpusServer::actingAs($user)->tool(CompleteNotationTool::class, ['notation_id' => $notation->id])->assertOk();
        $this->assertNotNull($notation->refresh()->completed_at);

        CorpusServer::actingAs($user)->tool(ListNotationsTool::class)->assertDontSee('Check this');
    }

    public function test_add_notation_rejects_an_assignee_who_cannot_see_the_page(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$user])->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        $outsider = User::factory()->create();

        CorpusServer::actingAs($user)->tool(AddNotationTool::class, [
            'page_id' => $page->id,
            'quote' => 'q',
            'from' => 0,
            'to' => 1,
            'body' => 'b',
            'assignee_id' => $outsider->id,
        ])->assertHasErrors(['The assignee cannot access this page.']);
    }

    public function test_complete_notation_is_forbidden_for_unrelated_users(): void
    {
        $notation = Notation::factory()->for(Page::factory()->create())->create();

        CorpusServer::actingAs(User::factory()->create())->tool(CompleteNotationTool::class, ['notation_id' => $notation->id])
            ->assertHasErrors(['You do not have permission to do that.']);
    }

    public function test_version_tools_list_and_restore(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['body' => 'Current']);
        $version = PageVersion::factory()->for($page)->create(['body' => 'Earlier text']);

        CorpusServer::actingAs($user)->tool(ListVersionsTool::class, ['id' => $page->id])->assertOk()->assertDontSee('Earlier text');

        CorpusServer::actingAs($user)->tool(RestoreVersionTool::class, ['id' => $page->id, 'version_id' => $version->id])->assertOk();

        $this->assertSame('Earlier text', $page->refresh()->body);
        $this->assertDatabaseHas('page_versions', ['page_id' => $page->id, 'reason' => 'pre-restore', 'body' => 'Current']);
    }

    public function test_restore_version_is_forbidden_for_viewers(): void
    {
        $viewer = User::factory()->create();
        $page = $this->privatePage($viewer);
        $version = PageVersion::factory()->for($page)->create();

        CorpusServer::actingAs($viewer)->tool(RestoreVersionTool::class, ['id' => $page->id, 'version_id' => $version->id])
            ->assertHasErrors(['You do not have permission to do that.']);
    }
}
