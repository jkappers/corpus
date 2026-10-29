<?php

namespace Tests\Feature;

use App\Models\Notation;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{quote: string, from: int, to: int, body: string}
     */
    private function payload(): array
    {
        return ['quote' => 'hello', 'from' => 1, 'to' => 6, 'body' => 'Fix this'];
    }

    public function test_assignee_defaults_to_author(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.notations.store', $page), $this->payload())
            ->assertCreated();

        $notation = Notation::firstOrFail();
        $this->assertSame($user->id, $notation->author_id);
        $this->assertSame($user->id, $notation->assignee_id);
        $this->assertSame($page->id, $notation->page_id);
    }

    public function test_can_assign_to_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->create(['visibility' => 'open']);
        $page = Page::factory()->for($user)->create(['space_id' => $space->id]);

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.notations.store', $page), [...$this->payload(), 'assignee_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($other->id, Notation::firstOrFail()->assignee_id);
    }

    public function test_cannot_assign_to_user_without_space_access(): void
    {
        $user = User::factory()->create();
        $outsider = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.notations.store', $page), [...$this->payload(), 'assignee_id' => $outsider->id])
            ->assertJsonValidationErrors('assignee_id');

        $this->assertDatabaseCount('notations', 0);
    }

    public function test_outsider_cannot_create_notation_on_private_page(): void
    {
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.pages.notations.store', $page), $this->payload())
            ->assertForbidden();
    }

    public function test_page_includes_notations(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Notation::factory()->for($page)->create(['author_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('pages.show', $page))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('notations', 1)->has('assignableUsers'));
    }

    public function test_inbox_lists_only_my_open_notations(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        Notation::factory()->for($page)->create(['author_id' => $user->id, 'body' => 'mine']);
        Notation::factory()->for($page)->completed()->create(['author_id' => $user->id]);
        Notation::factory()->for($page)->create(['author_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('inbox.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('inbox/index')
                ->has('notations', 1)
                ->where('notations.0.body', 'mine'));

        $this->actingAs($user)
            ->get(route('inbox.index', ['completed' => 1]))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('notations', 1));
    }

    public function test_inbox_lists_open_oldest_first_and_completed_newest_first(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        foreach (['open' => null, 'done' => now()] as $kind => $completedAt) {
            Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id, 'body' => "$kind old", 'completed_at' => $completedAt, 'created_at' => now()->subDays(2)]);
            Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id, 'body' => "$kind new", 'completed_at' => $completedAt, 'created_at' => now()]);
        }

        $this->actingAs($user)
            ->get(route('inbox.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('notations.0.body', 'open old'));

        $this->actingAs($user)
            ->get(route('inbox.index', ['completed' => 1]))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('notations.0.body', 'done new'));
    }

    public function test_inbox_rows_carry_derived_state_and_day_group(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        Notation::factory()->for($page)->create(['author_id' => $user->id, 'assignee_id' => $user->id, 'created_at' => now()->subDays(3)]);
        Notation::factory()->for($page)->create(['author_id' => $other->id, 'assignee_id' => $user->id, 'created_at' => now()]);

        $this->actingAs($user)
            ->get(route('inbox.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('notations.0.group', 'earlier')
                ->where('notations.0.is_self_assigned', true)
                ->where('notations.1.group', 'today')
                ->where('notations.1.is_self_assigned', false)
                ->where('notations.1.is_completed', false));
    }

    public function test_notations_report_what_the_viewer_may_do(): void
    {
        $author = User::factory()->create();
        $assignee = User::factory()->create();
        $space = Space::factory()->create(['visibility' => 'open']);
        $page = Page::factory()->for($author)->create(['space_id' => $space->id]);
        Notation::factory()->for($page)->create(['author_id' => $author->id, 'assignee_id' => $assignee->id]);

        $this->actingAs($assignee)->get(route('pages.show', $page))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('notations.0.can_complete', true)
                ->where('notations.0.can_delete', false));

        $this->actingAs($author)->get(route('pages.show', $page))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('notations.0.can_delete', true));
    }

    public function test_inbox_is_paginated_and_includes_space_context(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        Notation::factory()->count(27)->for($page)->create(['author_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('inbox.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('notations', 25)
                ->where('pagination.total', 27)
                ->where('pagination.last_page', 2)
                ->has('notations.0.page.space.name'));
    }

    public function test_assignee_can_complete_and_reopen(): void
    {
        $author = User::factory()->create();
        $assignee = User::factory()->create();
        $page = Page::factory()->for($author)->create(['space_id' => Space::factory()->create()->id]);
        $notation = Notation::factory()->for($page)->create([
            'author_id' => $author->id,
            'assignee_id' => $assignee->id,
        ]);

        $this->actingAs($assignee)->putJson(route('api.v1.notations.complete', $notation))->assertOk();
        $this->assertNotNull($notation->fresh()->completed_at);

        $this->actingAs($assignee)->deleteJson(route('api.v1.notations.reopen', $notation))->assertOk();
        $this->assertNull($notation->fresh()->completed_at);
    }

    public function test_unrelated_user_cannot_complete_or_delete(): void
    {
        $notation = Notation::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson(route('api.v1.notations.complete', $notation))->assertForbidden();
        $this->actingAs($stranger)->deleteJson(route('api.v1.notations.destroy', $notation))->assertForbidden();
    }

    public function test_author_can_delete(): void
    {
        $notation = Notation::factory()->for(Page::factory()->create(['space_id' => Space::factory()->create()->id]))->create();

        $this->actingAs($notation->author)->deleteJson(route('api.v1.notations.destroy', $notation))->assertNoContent();

        $this->assertModelMissing($notation);
    }
}
