<?php

namespace App\Models;

use Laravel\Passport\Client;

/**
 * A client may request only the scopes it was explicitly granted. Passport's default treats a client
 * with no scopes as allowed to request everything.
 */
class OAuthClient extends Client
{
    public function hasScope(string $scope): bool
    {
        return is_array($this->scopes) && $this->scopeExistsIn($scope, $this->scopes);
    }
}
