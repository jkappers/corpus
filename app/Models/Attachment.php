<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ulid
 * @property int $page_id
 * @property int $user_id
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['kind', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function url(): string
    {
        return route('attachments.show', $this, false);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attachments on live pages in spaces the user can see.
     *
     * @param  Builder<Attachment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('page', fn ($pages) => $pages
            ->withoutTrashed()
            ->whereHas('space', fn ($spaces) => $spaces->visibleTo($user)));
    }

    /**
     * @param  Builder<Attachment>  $query
     */
    public function scopeNamed(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        $query->when($term !== '', fn ($query) => $query->where('original_name', 'like', '%'.addcslashes($term, '%_\\').'%'));
    }
}
