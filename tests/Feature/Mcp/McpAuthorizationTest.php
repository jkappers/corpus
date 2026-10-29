<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class McpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function register(array $overrides = []): array
    {
        return $this->postJson(route('mcp.oauth.register'), [
            'client_name' => 'Test client',
            'redirect_uris' => ['https://client.example/callback'],
            ...$overrides,
        ])->json();
    }

    public function test_protected_resource_metadata_names_the_mcp_resource_and_authorization_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers.0', url('/'))
            ->assertJsonPath('scopes_supported.0', 'mcp:use');
    }

    public function test_authorization_server_metadata_requires_s256_and_advertises_registration(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);
    }

    public function test_unauthenticated_request_gets_401_with_resource_metadata_and_scope(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();

        $challenge = $response->headers->get('WWW-Authenticate');
        $this->assertStringStartsWith('Bearer ', $challenge);
        $this->assertStringContainsString('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"', $challenge);
        $this->assertStringContainsString('scope="mcp:use"', $challenge);
    }

    public function test_token_without_the_mcp_scope_gets_403_insufficient_scope(): void
    {
        Passport::actingAs(User::factory()->create(), ['other']);

        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertForbidden();

        $this->assertStringContainsString('error="insufficient_scope"', $response->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('scope="mcp:use"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_oauth_tokens_are_not_accepted_by_the_rest_api(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->withToken('test')->getJson(route('api.v1.me'))->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedRedirectUris(): array
    {
        return [
            'https' => ['https://client.example/callback'],
            'http loopback ip' => ['http://127.0.0.1:8123/callback'],
            'http localhost' => ['http://localhost:8123/callback'],
        ];
    }

    #[DataProvider('acceptedRedirectUris')]
    public function test_registration_creates_a_public_client_for_a_valid_redirect_uri(string $uri): void
    {
        $this->postJson(route('mcp.oauth.register'), ['client_name' => 'Test client', 'redirect_uris' => [$uri]])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('redirect_uris.0', $uri)
            ->assertJsonPath('grant_types', ['authorization_code', 'refresh_token'])
            ->assertJsonMissingPath('client_secret');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedRedirectUris(): array
    {
        return [
            'http on a public host' => ['http://client.example/callback'],
            'fragment' => ['https://client.example/callback#frag'],
            'unlisted custom scheme' => ['myapp://callback'],
            'not a url' => ['nope'],
        ];
    }

    #[DataProvider('rejectedRedirectUris')]
    public function test_registration_rejects_an_unsafe_redirect_uri(string $uri): void
    {
        $this->postJson(route('mcp.oauth.register'), ['client_name' => 'Test client', 'redirect_uris' => [$uri]])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_prune_command_removes_only_stale_clients_that_never_signed_in(): void
    {
        $stale = $this->register()['client_id'];
        $fresh = $this->register()['client_id'];
        $used = $this->register()['client_id'];
        Client::whereKey([$stale, $used])->update(['created_at' => now()->subDays(8)]);
        DB::table('oauth_auth_codes')->insert([
            'id' => 'code1', 'user_id' => User::factory()->create()->id, 'client_id' => $used,
            'scopes' => '[]', 'revoked' => false, 'expires_at' => now()->addMinute(),
        ]);

        $this->artisan('mcp:prune-clients')->assertSuccessful();

        $this->assertNull(Client::find($stale));
        $this->assertNotNull(Client::find($fresh));
        $this->assertNotNull(Client::find($used));
    }

    public function test_registration_requires_redirect_uris(): void
    {
        $this->postJson(route('mcp.oauth.register'), ['client_name' => 'Test client'])
            ->assertStatus(400);
    }

    public function test_authorize_rejects_a_resource_other_than_the_mcp_endpoint(): void
    {
        $client = $this->register();

        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($client, ['resource' => 'https://other.example/mcp'])))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_target');
    }

    public function test_authorize_rejects_plain_pkce(): void
    {
        $client = $this->register();

        $this->actingAs(User::factory()->create())
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($client, ['code_challenge_method' => 'plain'])))
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_token_endpoint_rejects_a_resource_other_than_the_mcp_endpoint(): void
    {
        $client = $this->register();

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'resource' => 'https://other.example/mcp',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_target');
    }

    public function test_full_authorization_code_flow_with_pkce_and_resource_yields_a_working_token(): void
    {
        $client = $this->register();
        $user = User::factory()->create();
        $verifier = str_repeat('a', 64);

        $this->actingAs($user)
            ->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($client, ['resource' => url('/mcp')], $verifier)))
            ->assertOk()
            ->assertSee('Test client')
            ->assertSee('https://client.example/callback');

        $this->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($client, [], $verifier)));
        $authToken = session('authToken');
        $this->assertNotNull($authToken);

        $redirect = $this->post('/oauth/authorize', ['state' => 'xyz', 'client_id' => $client['client_id'], 'auth_token' => $authToken])
            ->assertRedirect()
            ->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);

        $tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://client.example/callback',
            'code' => $query['code'],
            'code_verifier' => $verifier,
            'resource' => url('/mcp'),
        ])->assertOk()->json();

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertLessThanOrEqual(3600, $tokens['expires_in']);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withToken($tokens['access_token'])
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])
            ->assertOk();
    }

    public function test_self_registered_clients_cannot_obtain_api_scopes(): void
    {
        $client = $this->register();
        $admin = User::factory()->admin()->create();
        $verifier = str_repeat('c', 64);
        $query = $this->authorizeQuery($client, ['scope' => 'mcp:use admin:write admin:read pages:write'], $verifier);

        $this->actingAs($admin)->get('/oauth/authorize?'.http_build_query($query));
        $redirect = $this->post('/oauth/authorize', ['state' => 'xyz', 'client_id' => $client['client_id'], 'auth_token' => session('authToken')])
            ->headers->get('Location');
        parse_str(parse_url((string) $redirect, PHP_URL_QUERY), $callback);

        $tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://client.example/callback',
            'code' => $callback['code'],
            'code_verifier' => $verifier,
        ])->assertOk()->json();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withToken($tokens['access_token'])->getJson(route('api.v1.admin.users.index'))->assertForbidden();
        $this->withToken($tokens['access_token'])->getJson(route('api.v1.pages.index'))->assertForbidden();
    }

    public function test_token_exchange_fails_without_the_pkce_verifier(): void
    {
        $client = $this->register();
        $user = User::factory()->create();
        $verifier = str_repeat('b', 64);

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($client, [], $verifier)));
        $redirect = $this->post('/oauth/authorize', ['state' => 'xyz', 'client_id' => $client['client_id'], 'auth_token' => session('authToken')])
            ->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://client.example/callback',
            'code' => $query['code'],
        ])->assertStatus(400);
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function authorizeQuery(array $client, array $overrides = [], string $verifier = 'verifier'): array
    {
        return [
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://client.example/callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            ...$overrides,
        ];
    }
}
