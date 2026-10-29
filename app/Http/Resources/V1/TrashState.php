<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use Illuminate\Http\Resources\MissingValue;

/**
 * Timestamps shared by page, board and search result resources, with the purge date once trashed.
 */
class TrashState
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Page $page): array
    {
        $trashed = $page->hasAttribute('deleted_at') && $page->deleted_at !== null;

        return [
            'updated_at' => $page->hasAttribute('updated_at') ? $page->updated_at : new MissingValue,
            'deleted_at' => $page->hasAttribute('deleted_at') ? $page->deleted_at : new MissingValue,
            'purge_at' => $trashed ? $page->purgeAt() : new MissingValue,
            'days_left' => $trashed ? $page->daysUntilPurge() : new MissingValue,
        ];
    }
}
