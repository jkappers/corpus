<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FilesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_files_across_visible_spaces(): void
    {
        $user = User::factory()->create();
        $open = Space::factory()->create();
        $mine = Attachment::factory()->for(Page::factory()->for($user))->create();
        $shared = Attachment::factory()->for(Page::factory()->create(['space_id' => $open->id]))->create();

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('files/index')
                ->has('files', 2)
                ->where('pagination.total', 2)
                ->where('files.0.id', $shared->ulid)
                ->where('files.1.id', $mine->ulid));
    }

    public function test_it_hides_files_in_private_spaces_the_user_is_not_in(): void
    {
        $user = User::factory()->create();
        $secret = Space::factory()->private()->create();
        Attachment::factory()->for(Page::factory()->create(['space_id' => $secret->id]))->create();

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('files', 0));
    }

    public function test_it_hides_files_on_trashed_pages(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Attachment::factory()->for($page)->create();
        $page->delete();

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('files', 0));
    }

    public function test_it_filters_by_name_and_kind(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $report = Attachment::factory()->for($page)->file()->create(['original_name' => 'report.pdf']);
        Attachment::factory()->for($page)->create(['original_name' => 'photo.png']);

        $this->actingAs($user)
            ->get(route('files.index', ['q' => 'report']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('files', 1)
                ->where('files.0.id', $report->ulid)
                ->where('filters.q', 'report'));

        $this->actingAs($user)
            ->get(route('files.index', ['kind' => 'file']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('files', 1)
                ->where('files.0.id', $report->ulid));
    }

    public function test_it_rejects_an_unknown_kind(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('files.index', ['kind' => 'video']))
            ->assertSessionHasErrors('kind');
    }

    public function test_it_paginates(): void
    {
        $user = User::factory()->create();
        Attachment::factory()->count(26)->for(Page::factory()->for($user))->create();

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->has('files', 25)
                ->where('pagination.total', 26)
                ->where('pagination.last_page', 2));
    }
}
