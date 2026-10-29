<?php

namespace App\Actions;

use App\Models\User;
use App\Support\ApiScopes;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessTokenResult;
use RuntimeException;

class IssuePersonalAccessToken
{
    public function __construct(private ClientRepository $clients) {}

    /**
     * Issue a REST API token for the user. Administrators may grant admin scopes; everyone else may not.
     *
     * @param  list<string>  $scopes
     */
    public function handle(User $user, string $name, array $scopes): PersonalAccessTokenResult
    {
        $this->ensureClient($user);

        return $user->createToken($name, array_values(array_intersect($scopes, ApiScopes::availableTo($user))));
    }

    /**
     * The personal access client is created on first use and always allowed every API scope.
     */
    private function ensureClient(User $user): void
    {
        $provider = $user->getProviderName();

        try {
            $client = $this->clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            $client = $this->clients->createPersonalAccessGrantClient(config('app.name').' personal access tokens', $provider);
        }

        if ($client->scopes !== ApiScopes::all()) {
            $client->forceFill(['scopes' => ApiScopes::all()])->save();
        }
    }
}
