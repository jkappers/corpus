<?php

namespace App\Http\Resources\V1;

use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Space
 */
class SpaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'is_personal' => $this->isPersonal(),
            'visibility_locked_reason' => $this->visibilityLockedReason(),
            'membership_locked_reason' => $this->membershipLockedReason(),
            'members_count' => $this->whenCounted('users'),
            'pages_count' => $this->whenCounted('pages'),
            'members' => SpaceMemberResource::collection($this->whenLoaded('users')),
            $this->mergeWhen($user !== null, fn () => [
                ...$this->abilitiesFor($user),
                'can_update' => $user->can('update', $this->resource),
                'can_update_visibility' => $user->can('updateVisibility', $this->resource),
                'can_delete' => $user->can('delete', $this->resource),
                'delete_locked_reason' => $this->deletionLockedReason(),
            ]),
        ];
    }
}
