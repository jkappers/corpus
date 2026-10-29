<?php

namespace Tests\Feature\Api\V1;

use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_pages_and_boards_with_their_type_and_url(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['title' => 'Roadmap notes']);
        $board = Page::factory()->board()->for($user)->create(['title' => 'Roadmap board']);
        Page::factory()->create(['title' => 'Roadmap hidden', 'space_id' => Space::factory()->private()->create()->id]);

        $results = $this->actingAs($user)->getJson(route('api.v1.search', ['q' => 'Roadmap']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->collect('data')
            ->keyBy('id');

        $this->assertSame('page', $results[$page->id]['type']);
        $this->assertSame(route('api.v1.pages.show', $page), $results[$page->id]['links']['self']);
        $this->assertSame('board', $results[$board->id]['type']);
        $this->assertSame(route('api.v1.boards.show', $board), $results[$board->id]['links']['self']);
        $this->assertArrayNotHasKey('board', $results[$board->id]);
        $this->assertArrayNotHasKey('body', $results[$page->id]);
    }

    public function test_search_filters_by_type_and_lists_trash(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Live page']);
        Page::factory()->board()->for($user)->create(['title' => 'Live board']);
        Page::factory()->board()->for($user)->create(['title' => 'Gone board', 'deleted_at' => now()]);

        $this->actingAs($user)->getJson(route('api.v1.search', ['type' => 'board']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Live board');

        $this->getJson(route('api.v1.search', ['trashed' => 'only']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gone board')
            ->assertJsonPath('data.0.days_left', 30);

        $this->getJson(route('api.v1.search', ['type' => 'sheet']))->assertJsonValidationErrors('type');
    }

    public function test_search_requires_the_pages_read_scope(): void
    {
        Passport::actingAs(User::factory()->create(), ['spaces:read']);

        $this->withToken('test')->getJson(route('api.v1.search'))->assertForbidden();
    }
}
