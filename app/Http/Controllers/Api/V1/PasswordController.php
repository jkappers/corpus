<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;

/**
 * The signed-in user's own password. Session authentication only.
 */
#[Group('Account', weight: 7)]
class PasswordController extends Controller
{
    /**
     * Change your password.
     */
    public function update(PasswordUpdateRequest $request, ChangePassword $changePassword): Response
    {
        $changePassword->handle($request->user(), $request->validated('password'));

        return response()->noContent();
    }
}
