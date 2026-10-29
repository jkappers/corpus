<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($this->is($request->user()), $this->email),
            $this->mergeWhen($this->is($request->user()), fn () => [
                'email_verified_at' => $this->email_verified_at,
                'is_admin' => $this->is_admin,
                'is_owner' => $this->is_owner,
                'role' => $this->role(),
            ]),
        ];
    }
}
