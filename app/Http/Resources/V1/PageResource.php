<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A markdown page. Listings select a subset of columns, so `body` and `excerpt` appear only when loaded.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'space_id' => $this->space_id,
            'title' => $this->title,
            'excerpt' => $this->whenHas('excerpt'),
            'body' => $this->whenHas('body'),
            ...TrashState::for($this->resource),
            'space' => $this->whenLoaded('space', fn () => $this->space->only(['id', 'name', 'visibility'])),
        ];
    }
}
