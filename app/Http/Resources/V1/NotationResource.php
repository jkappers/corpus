<?php

namespace App\Http\Resources\V1;

use App\Models\Notation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notation
 */
class NotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->page_id,
            'quote' => $this->quote,
            'from' => $this->from,
            'to' => $this->to,
            'body' => $this->body,
            'completed_at' => $this->completed_at,
            'is_completed' => $this->completed_at !== null,
            'is_self_assigned' => $this->author_id === $this->assignee_id,
            'group' => $this->created_at?->isSameDay(now()) ? 'today' : 'earlier',
            'created_at' => $this->created_at,
            $this->mergeWhen($request->user() !== null, fn () => [
                'can_complete' => $request->user()->can('complete', $this->resource),
                'can_delete' => $request->user()->can('delete', $this->resource),
                'can_reply' => $request->user()->can('reply', $this->resource),
            ]),
            'author' => new UserResource($this->whenLoaded('author')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'resolved_by' => new UserResource($this->whenLoaded('resolvedBy')),
            'replies' => NotationReplyResource::collection($this->whenLoaded('replies')),
            'page' => new PageResource($this->whenLoaded('page')),
        ];
    }
}
