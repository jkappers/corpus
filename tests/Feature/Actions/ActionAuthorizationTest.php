<?php

namespace Tests\Feature\Actions;

use App\Actions\AddSpaceMember;
use App\Actions\CreateNotation;
use App\Actions\CreatePage;
use App\Actions\CreateSpace;
use App\Actions\DeleteNotation;
use App\Actions\InviteUserAsAdmin;
use App\Actions\RestorePageVersion;
use App\Actions\SetNotationCompleted;
use App\Actions\StoreAttachment;
use App\Actions\TrashPage;
use App\Actions\UpdateNotation;
use App\Actions\UpdatePage;
use App\Actions\UpdateUser;
use App\Enums\AuditEvent;
use App\Enums\PageType;
use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Notation;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Actions enforce authorization and domain rules themselves, so any adapter that calls them is covered.
 */
class ActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_page_refuses_users_who_cannot_edit_it(): void
    {
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePage::class)->handle($page, User::factory()->create(), ['title' => 'x']);
    }

    public function test_update_page_refuses_a_move_into_a_space_the_user_cannot_edit(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        $locked = Space::factory()->private()->create();

        try {
            app(UpdatePage::class)->handle($page, $user, ['space_id' => $locked->id]);
            $this->fail('The move should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('space_id', $exception->errors());
        }

        $this->assertNotSame($locked->id, $page->fresh()->space_id);
    }

    public function test_update_user_requires_an_admin(): void
    {
        $this->expectException(AuthorizationException::class);

        app(UpdateUser::class)->handle(User::factory()->create(), User::factory()->create(), ['name' => 'x']);
    }

    public function test_update_user_protects_the_owner_role_and_self_demotion(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        foreach ([[$owner, $admin], [$admin, $admin]] as [$target, $actor]) {
            try {
                app(UpdateUser::class)->handle($target, $actor, [], 'member');
                $this->fail('The role change should have been refused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('role', $exception->errors());
            }
        }

        $this->assertTrue($owner->fresh()->is_admin);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_write_actions_refuse_users_without_access(): void
    {
        $stranger = User::factory()->create();
        $page = Page::factory()->create(['space_id' => Space::factory()->private()->create()->id]);
        $notation = Notation::factory()->for($page)->create();
        $team = Space::factory()->create();

        $attempts = [
            'create page' => fn () => app(CreatePage::class)->handle($stranger, PageType::Page, $page->space),
            'create notation' => fn () => app(CreateNotation::class)->handle($page, $stranger, ['quote' => 'q', 'from' => 0, 'to' => 1, 'body' => 'b']),
            'update notation' => fn () => app(UpdateNotation::class)->handle($notation, $stranger, ['body' => 'x']),
            'delete notation' => fn () => app(DeleteNotation::class)->handle($notation, $stranger),
            'complete notation' => fn () => app(SetNotationCompleted::class)->handle($notation, $stranger),
            'restore version' => fn () => app(RestorePageVersion::class)->handle($page, PageVersion::factory()->for($page)->create(), $stranger),
            'store attachment' => fn () => app(StoreAttachment::class)->handle($page, $stranger, UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')),
            'create space' => fn () => app(CreateSpace::class)->handle($stranger, 'Team', SpaceVisibility::Open),
            'add member' => fn () => app(AddSpaceMember::class)->handle($team, $stranger, 'x@example.com', SpaceRole::Editor),
            'invite user' => fn () => app(InviteUserAsAdmin::class)->handle($stranger, 'X', 'x@example.com'),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("{$name} should have been refused.");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNull(User::where('email', 'x@example.com')->first());
        $this->assertSame(1, Page::count());
    }

    public function test_create_notation_refuses_an_assignee_who_cannot_see_the_page(): void
    {
        $author = User::factory()->create();
        $page = Page::factory()->for($author)->create();

        try {
            app(CreateNotation::class)->handle($page, $author, [
                'quote' => 'q', 'from' => 0, 'to' => 1, 'body' => 'b', 'assignee_id' => User::factory()->create()->id,
            ]);
            $this->fail('The assignee should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['The assignee cannot access this page.'], $exception->errors()['assignee_id']);
        }

        $this->assertSame(0, Notation::count());
    }

    public function test_restore_page_version_refuses_a_version_of_another_page(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create(['title' => 'Mine']);
        $foreign = PageVersion::factory()->for(Page::factory()->for($user))->create(['title' => 'Other']);

        $this->expectException(ModelNotFoundException::class);

        try {
            app(RestorePageVersion::class)->handle($page, $foreign, $user);
        } finally {
            $this->assertSame('Mine', $page->fresh()->title);
        }
    }

    public function test_audit_entries_name_the_acting_user_without_a_session(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->assertGuest();

        app(TrashPage::class)->handle($page, $user);

        $this->assertDatabaseHas('audit_logs', ['event' => AuditEvent::PageDeleted->value, 'actor_id' => $user->id]);
    }

    public function test_notes_and_files_cannot_be_added_to_a_board(): void
    {
        $user = User::factory()->create();
        $board = Page::factory()->board()->for($user)->create();

        foreach ([
            'notation' => fn () => app(CreateNotation::class)->handle($board, $user, ['quote' => 'q', 'from' => 0, 'to' => 1, 'body' => 'b']),
            'attachment' => fn () => app(StoreAttachment::class)->handle($board, $user, UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')),
        ] as $name => $attempt) {
            try {
                $attempt();
                $this->fail("Adding a {$name} to a board should have been refused.");
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $board->notations()->count() + $board->attachments()->count());
    }
}
