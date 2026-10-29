<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\SendPasswordReset;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Admin', weight: 9)]
class UserPasswordResetController extends Controller
{
    /**
     * Send a password reset email.
     */
    public function store(Request $request, User $user, SendPasswordReset $sendReset): Response
    {
        $sendReset->handle($user, $request->user());

        return response()->noContent();
    }
}
