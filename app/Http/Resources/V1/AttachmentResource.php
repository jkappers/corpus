<?php

namespace App\Http\Resources\V1;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'url' => $this->url(),
            'name' => $this->original_name,
            'kind' => $this->kind,
            'mime' => $this->mime_type,
            'size' => $this->size,
            'created_at' => $this->created_at,
            'page' => new PageResource($this->whenLoaded('page')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
