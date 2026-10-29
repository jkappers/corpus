<?php

namespace Tests\Feature;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_upload_or_view_files(): void
    {
        $page = Page::factory()->create();

        $this->postJson(route('api.v1.pages.attachments.store', $page))->assertUnauthorized();
        $this->get(route('attachments.show', Attachment::factory()->for($page)->create()))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_upload_an_image(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.pages.attachments.store', $page), [
            'file' => UploadedFile::fake()->image('diagram.png'),
        ]);

        $attachment = $page->attachments()->sole();

        $response->assertCreated()->assertJson(['data' => [
            'id' => $attachment->ulid,
            'url' => "/attachments/{$attachment->ulid}",
            'name' => 'diagram.png',
            'kind' => 'image',
            'mime' => 'image/png',
            'size' => $attachment->size,
        ]]);
        $this->assertSame(AttachmentKind::Image, $attachment->kind);
        $this->assertSame($user->id, $attachment->user_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    /**
     * @return array<string, array{UploadedFile}>
     */
    public static function validFiles(): array
    {
        return [
            'pdf' => [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')],
            'docx' => [UploadedFile::fake()->create('report.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            'pptx' => [UploadedFile::fake()->create('deck.pptx', 10, 'application/vnd.openxmlformats-officedocument.presentationml.presentation')],
            'markdown' => [UploadedFile::fake()->createWithContent('notes.md', "# Notes\n")],
            'mp4' => [UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4')],
            'mp3' => [UploadedFile::fake()->create('song.mp3', 10, 'audio/mpeg')],
            'wav' => [UploadedFile::fake()->create('take.wav', 10, 'audio/x-wav')],
            '7z' => [UploadedFile::fake()->create('backup.7z', 10, 'application/x-7z-compressed')],
            'tar' => [UploadedFile::fake()->create('backup.tar', 10, 'application/x-tar')],
        ];
    }

    public function test_large_media_gets_a_higher_size_limit(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $url = route('api.v1.pages.attachments.store', $page);

        $this->actingAs($user)
            ->postJson($url, ['file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
            ->assertJsonValidationErrors('file');
        $this->actingAs($user)
            ->postJson($url, ['file' => UploadedFile::fake()->create('big.mp4', 10241, 'video/mp4')])
            ->assertCreated();
        $this->actingAs($user)
            ->postJson($url, ['file' => UploadedFile::fake()->create('huge.mp4', 102401, 'video/mp4')])
            ->assertJsonValidationErrors('file');
    }

    #[DataProvider('validFiles')]
    public function test_user_can_upload_a_non_image_file(UploadedFile $file): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.attachments.store', $page), ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'file')
            ->assertJsonPath('data.name', $file->getClientOriginalName());

        $attachment = $page->attachments()->sole();
        $this->assertSame(AttachmentKind::File, $attachment->kind);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_show_downloads_non_image_files_as_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $attachment = Attachment::factory()->file()->for($page)->create();
        Storage::disk('local')->put($attachment->path, 'x');

        $this->actingAs($user)->get($attachment->url())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', "attachment; filename={$attachment->original_name}");
    }

    public function test_show_streams_media_inline_with_range_support(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $attachment = Attachment::factory()->for($page)->create([
            'kind' => AttachmentKind::File,
            'path' => 'attachments/clip.mp4',
            'original_name' => 'clip.mp4',
            'mime_type' => 'video/mp4',
        ]);
        Storage::disk('local')->put($attachment->path, '0123456789');

        $this->actingAs($user)
            ->get($attachment->url(), ['Range' => 'bytes=0-3'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=clip.mp4');
    }

    public function test_upload_by_user_without_access_is_forbidden(): void
    {
        Storage::fake('local');
        $page = Page::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.pages.attachments.store', $page), [
                'file' => UploadedFile::fake()->image('diagram.png'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    /**
     * @return array<string, array{UploadedFile}>
     */
    public static function invalidFiles(): array
    {
        return [
            'svg' => [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'html' => [UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')],
            'exe' => [UploadedFile::fake()->create('setup.exe', 10, 'application/x-msdownload')],
            'oversize' => [UploadedFile::fake()->image('huge.png')->size(10241)],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_upload_rejects_invalid_files(UploadedFile $file): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.attachments.store', $page), ['file' => $file])
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_upload_requires_a_file(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('api.v1.pages.attachments.store', $page))
            ->assertJsonValidationErrors('file');
    }

    public function test_show_streams_the_image_inline(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $this->actingAs($user)->postJson(route('api.v1.pages.attachments.store', $page), [
            'file' => UploadedFile::fake()->image('diagram.png'),
        ]);
        $attachment = $page->attachments()->sole();

        $this->actingAs($user)->get($attachment->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=diagram.png');
    }

    public function test_show_is_forbidden_without_space_access(): void
    {
        Storage::fake('local');
        $attachment = Attachment::factory()->create();
        Storage::disk('local')->put($attachment->path, 'x');

        $this->actingAs(User::factory()->create())
            ->get($attachment->url())
            ->assertForbidden();
    }

    public function test_show_still_works_for_trashed_pages(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $attachment = Attachment::factory()->for($page)->create();
        Storage::disk('local')->put($attachment->path, 'x');
        $page->delete();

        $this->actingAs($user)->get($attachment->url())->assertOk();
    }

    public function test_force_deleting_a_page_removes_its_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $attachment = Attachment::factory()->for($page)->create();
        Storage::disk('local')->put($attachment->path, 'x');
        $page->delete();

        $this->actingAs($user)
            ->deleteJson(route('api.v1.pages.force-destroy', $page->id))
            ->assertNoContent();

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseCount('attachments', 0);
    }
}
