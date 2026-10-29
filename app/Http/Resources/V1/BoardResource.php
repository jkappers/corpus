<?php

namespace App\Http\Resources\V1;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A board. Its canvas content (nodes, edges, viewport) is exposed as `board` because a top-level `data` key
 * stops Laravel wrapping the response. Listings leave it out.
 *
 * @mixin Page
 */
class BoardResource extends JsonResource
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
            'board' => $this->whenHas('data'),
            ...TrashState::for($this->resource),
            'space' => $this->whenLoaded('space', fn () => $this->space->only(['id', 'name', 'visibility'])),
        ];
    }
}
