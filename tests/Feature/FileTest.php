<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_space_lists_only_its_own_files(): void
    {
        $user = User::factory()->create();
        $shared = Space::factory()->create();
        $mine = Page::factory()->for($user)->create();
        $elsewhere = Page::factory()->for($user)->create(['space_id' => $shared->id]);

        $shown = Attachment::factory()->for($mine)->file()->create(['original_name' => 'quarterly-report.pdf']);
        Attachment::factory()->for($elsewhere)->file()->create();

        $this->actingAs($user)
            ->get(route('spaces.show', [$user->personalSpace, 'type' => 'file']))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('pages/index')
                ->where('type', 'file')
                ->has('pages', 0)
                ->has('files', 1)
                ->where('files.0.id', $shown->ulid)
                ->where('files.0.page.id', $mine->id));
    }

    public function test_files_on_trashed_pages_are_hidden(): void
    {
        $user = User::factory()->create();
        $trashed = Page::factory()->for($user)->create();
        Attachment::factory()->for($trashed)->create();
        $trashed->delete();

        $this->actingAs($user)
            ->get(route('spaces.show', [$user->personalSpace, 'type' => 'file']))
            ->assertInertia(fn (Assert $inertia) => $inertia->has('files', 0));
    }

    public function test_other_listings_do_not_load_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('spaces.show', $user->personalSpace))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('files', null));
    }
}
