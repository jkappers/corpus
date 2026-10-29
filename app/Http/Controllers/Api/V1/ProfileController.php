<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\V1\UserResource;
use Dedoc\Scramble\Attributes\Group;

/**
 * The signed-in user's own profile. Session authentication only.
 */
#[Group('Account', weight: 7)]
class ProfileController extends Controller
{
    /**
     * Update your profile.
     *
     * Changing the email address clears its verification.
     */
    public function update(ProfileUpdateRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return new UserResource($updateProfile->handle($request->user(), $request->validated()));
    }
}
