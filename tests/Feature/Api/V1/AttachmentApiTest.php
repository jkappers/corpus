<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attachment;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_uploads_a_file_and_returns_201(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.attachments.store', $page), [
            'file' => UploadedFile::fake()->image('diagram.png'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'diagram.png')
            ->assertJsonPath('data.kind', 'image');

        Storage::disk('local')->assertExists($page->attachments()->sole()->path);
    }

    public function test_store_requires_a_file(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('api.v1.pages.attachments.store', $page), [])
            ->assertJsonValidationErrors('file');
    }

    public function test_store_forbids_users_who_cannot_edit_the_page(): void
    {
        Storage::fake('local');
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.pages.attachments.store', $page), ['file' => UploadedFile::fake()->image('a.png')])
            ->assertForbidden();
    }

    public function test_index_lists_visible_files_and_filters_by_kind_and_name(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $image = Attachment::factory()->for($page)->create(['original_name' => 'photo.png']);
        Attachment::factory()->file()->for($page)->create(['original_name' => 'report.pdf']);
        Attachment::factory()->for(Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]))->create();

        $this->actingAs($user)->getJson(route('api.v1.attachments.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('api.v1.attachments.index', ['kind' => 'image', 'q' => 'photo']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $image->ulid);
    }
}
