<?php

namespace App\Actions;

use App\Models\User;
use Laravel\Passport\Token;

class RevokePersonalAccessToken
{
    public function handle(User $user, Token $token): void
    {
        abort_unless($token->user_id === $user->getKey(), 404);

        $token->revoke();
    }
}
