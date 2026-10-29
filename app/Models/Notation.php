<?php

namespace App\Models;

use Database\Factories\NotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property int $author_id
 * @property int $assignee_id
 * @property string $quote
 * @property int $from
 * @property int $to
 * @property string $body
 * @property Carbon|null $completed_at
 * @property int|null $resolved_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['quote', 'from', 'to', 'body', 'assignee_id'])]
class Notation extends Model
{
    /** @use HasFactory<NotationFactory> */
    use HasFactory;

    public const QUOTE_MAX = 2000;

    public const BODY_MAX = 5000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /**
     * @return HasMany<NotationReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(NotationReply::class)->chaperone('notation')->oldest()->orderBy('id');
    }

    /**
     * @param  Builder<Notation>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<Notation>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->where('assignee_id', $user->id);
    }

    /**
     * Notations assigned to (or, with $createdByUser, written by) the user on pages they can still see.
     *
     * @param  Builder<Notation>  $query
     */
    public function scopeInboxFor(Builder $query, User $user, bool $completed = false, bool $createdByUser = false): void
    {
        $query->where($createdByUser ? 'author_id' : 'assignee_id', $user->id)
            ->when($completed, fn ($query) => $query->whereNotNull('completed_at'), fn ($query) => $query->open())
            ->whereHas('page.space', fn ($spaces) => $spaces->visibleTo($user));
    }

    /**
     * Match the body, the quoted text, or the page title.
     *
     * @param  Builder<Notation>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn ($query) => $query
            ->where('body', 'like', $like)
            ->orWhere('quote', 'like', $like)
            ->orWhereHas('page', fn ($pages) => $pages->titleLike($term)));
    }
}
