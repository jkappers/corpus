<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SpaceRole;
use App\Models\Notation;
use App\Models\NotationReply;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{quote: string, from: int, to: int, body: string}
     */
    private function payload(): array
    {
        return ['quote' => 'hello', 'from' => 1, 'to' => 6, 'body' => 'Fix this'];
    }

    public function test_store_creates_a_notation_assigned_to_the_author_by_default(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.notations.store', $page), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.assignee.id', $user->id)
            ->assertJsonPath('data.author.id', $user->id);

        $this->assertSame($page->id, Notation::sole()->page_id);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.notations.store', $page), [])
            ->assertJsonValidationErrors(['quote', 'from', 'to', 'body']);
    }

    public function test_store_forbids_users_who_cannot_view_the_page(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.pages.notations.store', $page), $this->payload())
            ->assertForbidden();
    }

    public function test_for_page_lists_the_page_notations(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);

        $this->actingAs($user)->getJson(route('api.v1.pages.notations.index', $page))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_returns_open_notations_assigned_to_the_user_and_can_switch_to_completed(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $open = Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);
        $done = Notation::factory()->completed()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);
        Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => User::factory()]);

        $this->actingAs($user)->getJson(route('api.v1.notations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);

        $this->getJson(route('api.v1.notations.index', ['status' => 'completed']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $done->id);
    }

    public function test_complete_and_reopen_toggle_completed_at(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);

        $this->actingAs($user)->putJson(route('api.v1.notations.complete', $notation))->assertOk();
        $this->assertNotNull($notation->refresh()->completed_at);

        $this->deleteJson(route('api.v1.notations.reopen', $notation))->assertOk();
        $this->assertNull($notation->refresh()->completed_at);
    }

    public function test_resolving_records_who_closed_the_thread_and_reopening_clears_it(): void
    {
        $author = User::factory()->create();
        $assignee = User::factory()->create();
        $space = Space::factory()->create(['visibility' => 'open']);
        $page = Page::factory()->create(['space_id' => $space->id]);
        $notation = Notation::factory()->for($page)->create(['author_id' => $author->id, 'assignee_id' => $assignee->id]);

        $this->actingAs($assignee)->putJson(route('api.v1.notations.complete', $notation))
            ->assertOk()
            ->assertJsonPath('data.resolved_by.id', $assignee->id);
        $this->assertSame($assignee->id, $notation->refresh()->resolved_by_id);

        $this->deleteJson(route('api.v1.notations.reopen', $notation))->assertOk();
        $this->assertNull($notation->refresh()->resolved_by_id);
    }

    public function test_reply_adds_to_an_open_thread(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$author, $replier], SpaceRole::Editor)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        $notation = Notation::factory()->for($page)->create(['author_id' => $author->id]);

        $this->actingAs($replier)->postJson(route('api.v1.notations.replies.store', $notation), ['body' => 'On it'])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $replier->id)
            ->assertJsonPath('data.can_delete', true);

        $reply = NotationReply::sole();
        $this->assertSame($notation->id, $reply->notation_id);
        $this->assertSame('On it', $reply->body);
    }

    public function test_reply_requires_a_body(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->for($page)->create(['author_id' => $user->id]);

        $this->actingAs($user)->postJson(route('api.v1.notations.replies.store', $notation), ['body' => ''])
            ->assertJsonValidationErrors('body');
    }

    public function test_reply_is_refused_on_a_resolved_thread(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->completed()->for($page)->create(['author_id' => $user->id]);

        $this->actingAs($user)->postJson(route('api.v1.notations.replies.store', $notation), ['body' => 'Late'])
            ->assertForbidden();

        $this->assertSame(0, NotationReply::count());
    }

    public function test_reply_is_refused_for_readers_and_outsiders(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$author], SpaceRole::Editor)->withMembers([$viewer], SpaceRole::Viewer)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        $notation = Notation::factory()->for($page)->create(['author_id' => $author->id]);

        $this->actingAs($viewer)->postJson(route('api.v1.notations.replies.store', $notation), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($outsider)->postJson(route('api.v1.notations.replies.store', $notation), ['body' => 'Hi'])->assertForbidden();
    }

    public function test_reply_author_and_admins_can_delete_a_reply_but_others_cannot(): void
    {
        $author = User::factory()->create();
        $replier = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $space = Space::factory()->create(['visibility' => 'open']);
        $page = Page::factory()->create(['space_id' => $space->id]);
        $notation = Notation::factory()->for($page)->create(['author_id' => $author->id]);
        $reply = NotationReply::factory()->for($notation)->create(['author_id' => $replier->id]);

        $this->actingAs($author)->deleteJson(route('api.v1.notation-replies.destroy', $reply))->assertForbidden();
        $this->actingAs($replier)->deleteJson(route('api.v1.notation-replies.destroy', $reply))->assertNoContent();

        $again = NotationReply::factory()->for($notation)->create(['author_id' => $replier->id]);
        $this->actingAs($admin)->deleteJson(route('api.v1.notation-replies.destroy', $again))->assertNoContent();
        $this->assertSame(0, NotationReply::count());
    }

    public function test_deleting_a_notation_removes_its_replies(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->for($page)->create(['author_id' => $user->id]);
        NotationReply::factory()->count(2)->for($notation)->create();

        $this->actingAs($user)->deleteJson(route('api.v1.notations.destroy', $notation))->assertNoContent();

        $this->assertSame(0, NotationReply::count());
    }

    public function test_complete_forbids_users_who_are_neither_author_nor_assignee(): void
    {
        $page = Page::factory()->create();
        $notation = Notation::factory()->for($page)->create();

        $this->actingAs(User::factory()->create())
            ->putJson(route('api.v1.notations.complete', $notation))
            ->assertForbidden();
    }

    public function test_update_and_destroy_work_for_the_author(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);

        $this->actingAs($user)->patchJson(route('api.v1.notations.update', $notation), ['body' => 'Changed'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Changed');

        $this->deleteJson(route('api.v1.notations.destroy', $notation))->assertNoContent();
        $this->assertModelMissing($notation);
    }

    public function test_update_refuses_to_reassign_to_a_user_who_cannot_see_the_page(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $notation = Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id]);
        $outsider = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('api.v1.notations.update', $notation), ['assignee_id' => $outsider->id])
            ->assertJsonValidationErrors(['assignee_id' => 'The assignee cannot access this page.']);

        $this->assertSame($user->id, $notation->fresh()->assignee_id);
    }

    public function test_listing_notes_does_not_query_per_note(): void
    {
        $member = User::factory()->create();
        $space = Space::factory()->private()->withMembers([$member], SpaceRole::Editor)->create();
        $page = Page::factory()->create(['space_id' => $space->id]);
        Notation::factory()->count(12)->for($page)->create(['author_id' => $member->id, 'assignee_id' => $member->id])
            ->each(fn (Notation $notation) => NotationReply::factory()->count(2)->for($notation)->create());

        $this->actingAs($member);
        DB::enableQueryLog();

        $this->getJson(route('api.v1.pages.notations.index', $page))
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonCount(2, 'data.0.replies');

        $this->assertLessThan(15, count(DB::getQueryLog()));
    }
}
