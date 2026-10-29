<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;

class PersonalAccessTokenQuery
{
    /**
     * The user's active personal access tokens, newest first.
     *
     * @return Builder<Token>
     */
    public function handle(User $user): Builder
    {
        return Token::query()
            ->where('user_id', $user->getKey())
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->whereHas('client', fn (Builder $query) => $query->where('grant_types', 'like', '%personal_access%'))
            ->latest();
    }
}
