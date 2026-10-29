<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as seen by an administrator: contact details, role and what the viewer may do to them.
 *
 * @mixin User
 */
class ManagedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role(),
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'assignable_roles' => $this->role() === 'owner'
                ? [['value' => 'owner', 'label' => 'Owner']]
                : [['value' => 'member', 'label' => 'Member'], ['value' => 'admin', 'label' => 'Admin']],
            'can_delete' => $viewer->can('delete', $this->resource),
            'can_update_role' => $viewer->can('updateRole', $this->resource) && ! $viewer->is($this->resource),
        ];
    }
}
