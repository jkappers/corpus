<?php

namespace App\Models;

use App\Actions\RecordAuditLog;
use App\Enums\AuditEvent;
use App\Enums\PageType;
use Carbon\CarbonInterface;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $space_id
 * @property PageType $type
 * @property string $title
 * @property string|null $body
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['type', 'title', 'body', 'data'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, Prunable, SoftDeletes;

    public const TRASH_RETENTION_DAYS = 30;

    public const UNTITLED = 'Untitled';

    public const TITLE_MAX = 255;

    public const BODY_MAX = 1_000_000;

    protected static function booted(): void
    {
        static::forceDeleting(function (Page $page) {
            $page->attachments->each->delete();
        });
    }

    /**
     * Trash older than the retention window is removed by the scheduler, with no actor.
     */
    protected function pruning(): void
    {
        $audit = app(RecordAuditLog::class);

        foreach ($this->attachments as $attachment) {
            $audit->handle(AuditEvent::AttachmentDeleted, null, $attachment, ['page_id' => $this->id]);
        }

        $audit->handle(AuditEvent::PageForceDeleted, null, $this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PageType::class,
            'data' => 'array',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::TRASH_RETENTION_DAYS));
    }

    /**
     * @return array{nodes: list<mixed>, edges: list<mixed>, viewport: array{x: int, y: int, zoom: int}}
     */
    public static function emptyBoard(): array
    {
        return [
            'nodes' => [],
            'edges' => [],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return HasMany<Notation, $this>
     */
    public function notations(): HasMany
    {
        return $this->hasMany(Notation::class);
    }

    /**
     * @return HasMany<PageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pages in spaces the user can see.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('space', fn ($spaces) => $spaces->visibleTo($user));
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeTitleLike(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        $query->when($term !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'));
    }

    public function purgeAt(): ?CarbonInterface
    {
        return $this->deleted_at?->copy()->addDays(self::TRASH_RETENTION_DAYS);
    }

    public function daysUntilPurge(): ?int
    {
        $purgeAt = $this->purgeAt();

        return $purgeAt === null ? null : max(0, (int) ceil(now()->diffInDays($purgeAt, false)));
    }

    /**
     * Normalise requested listing order to a known column and direction.
     *
     * @return array{sort: string, direction: string}
     */
    public static function resolveSort(?string $sort, ?string $direction): array
    {
        $sort = in_array($sort, ['title', 'space', 'updated'], true) ? $sort : 'updated';
        $direction = in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : ($sort === 'updated' ? 'desc' : 'asc');

        return ['sort' => $sort, 'direction' => $direction];
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeSortedBy(Builder $query, ?string $sort, ?string $direction): void
    {
        ['sort' => $sort, 'direction' => $direction] = self::resolveSort($sort, $direction);

        match ($sort) {
            'title' => $query->orderByRaw('lower(pages.title) '.$direction),
            'space' => $query->orderBy(
                Space::query()->selectRaw('lower(spaces.name)')->whereColumn('spaces.id', 'pages.space_id'),
                $direction,
            ),
            default => $query->orderBy('pages.updated_at', $direction),
        };

        $query->orderByDesc('pages.id');
    }

    /**
     * Listing columns plus a short body excerpt, without the full body or board data.
     *
     * @param  Builder<Page>  $query
     */
    public function scopeForListing(Builder $query): void
    {
        $query->with('space:id,name,visibility')
            ->select(['id', 'space_id', 'type', 'title', 'updated_at', 'deleted_at'])
            ->selectRaw('substr(body, 1, 160) as excerpt');
    }
}
