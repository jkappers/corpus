<?php

namespace App\Actions;

use App\Enums\AuditEvent;
use App\Enums\PageType;
use App\Models\Page;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePage
{
    public function __construct(private RecordAuditLog $auditLog) {}

    /**
     * Create a page or board in the given space, defaulting to the user's personal space.
     * Pages take an optional markdown `body`; boards take optional canvas `data`.
     *
     * @param  array{title?: ?string, body?: ?string, data?: ?array<string, mixed>}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(User $user, PageType $type, ?Space $space = null, array $attributes = []): Page
    {
        $space ??= $user->personalSpace;

        Gate::forUser($user)->authorize('create', [Page::class, $space]);

        $type->ensureAcceptsContent($attributes);

        $title = trim((string) ($attributes['title'] ?? ''));

        $page = $space->pages()->make([
            'type' => $type,
            'title' => $title === '' ? Page::UNTITLED : $title,
            'body' => $type === PageType::Page ? ($attributes['body'] ?? '') : null,
            'data' => $type === PageType::Board ? ($attributes['data'] ?? Page::emptyBoard()) : null,
        ]);
        $page->user()->associate($user);
        $page->save();

        $this->auditLog->handle(AuditEvent::PageCreated, $user, $page, ['type' => $type->value]);

        return $page;
    }
}
