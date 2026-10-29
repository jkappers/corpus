<?php

namespace App\Http\Resources\V1;

use App\Models\PageVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageVersion
 */
class PageVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'reason' => $this->reason,
            'body' => $this->whenHas('body'),
            'board' => $this->whenHas('data'),
            'summary' => $this->when(
                $this->resource->hasAttribute('data') || $this->resource->hasAttribute('body'),
                fn () => $this->body ?? count($this->data['nodes'] ?? []).' nodes',
            ),
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}
