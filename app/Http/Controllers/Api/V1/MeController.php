<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Account', 'Your own profile and credentials. Changing the profile or password and managing tokens need a signed-in session; access tokens get 403.', weight: 7)]
class MeController extends Controller
{
    /**
     * Get your profile.
     */
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
