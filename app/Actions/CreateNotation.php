<?php

namespace App\Actions;

use App\Models\Notation;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateNotation
{
    public function __construct(private EnsureAssigneeCanAccessPage $ensureAssignee) {}

    /**
     * Anchor a note on the page. The assignee defaults to the author and must be able to see the page.
     *
     * @param  array{quote: string, from: int, to: int, body: string, assignee_id?: ?int}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(Page $page, User $author, array $attributes): Notation
    {
        Gate::forUser($author)->authorize('create', [Notation::class, $page]);

        $assigneeId = $attributes['assignee_id'] ?? $author->id;
        unset($attributes['assignee_id']);

        $this->ensureAssignee->handle($page, $assigneeId);

        $notation = $page->notations()->make($attributes);
        $notation->author()->associate($author);
        $notation->assignee_id = $assigneeId;
        $notation->save();

        return $notation;
    }
}
