<?php

namespace App\Http\Resources\V1;

use App\Enums\PageType;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item in a mixed listing of pages and boards: a summary without content.
 * `type` says which resource it is, and `links.self` is its canonical URL, so clients never build URLs from the type.
 * Clients should skip types they do not know; more may be added.
 *
 * @mixin Page
 */
class SearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'space_id' => $this->space_id,
            'title' => $this->title,
            'excerpt' => $this->when($this->type === PageType::Page && $this->resource->hasAttribute('excerpt'), fn () => $this->excerpt),
            ...TrashState::for($this->resource),
            'space' => $this->whenLoaded('space', fn () => $this->space->only(['id', 'name', 'visibility'])),
            'links' => [
                'self' => route($this->type === PageType::Board ? 'api.v1.boards.show' : 'api.v1.pages.show', $this->id),
            ],
        ];
    }
}
