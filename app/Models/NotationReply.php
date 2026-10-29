<?php

namespace App\Models;

use Database\Factories\NotationReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notation_id
 * @property int $author_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['body'])]
class NotationReply extends Model
{
    /** @use HasFactory<NotationReplyFactory> */
    use HasFactory;

    public const BODY_MAX = 5000;

    /**
     * @return BelongsTo<Notation, $this>
     */
    public function notation(): BelongsTo
    {
        return $this->belongsTo(Notation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
