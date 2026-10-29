<?php

namespace App\Http\Resources\V1;

use App\Models\NotationReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotationReply
 */
class NotationReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notation_id' => $this->notation_id,
            'body' => $this->body,
            'created_at' => $this->created_at,
            $this->mergeWhen($request->user() !== null, fn () => [
                'can_delete' => $request->user()->can('deleteReply', [$this->notation, $this->resource]),
            ]),
            'author' => new UserResource($this->whenLoaded('author')),
        ];
    }
}
