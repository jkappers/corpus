<?php

namespace Tests\Feature\Api\V1;

use App\Models\AuditLog;
use App\Models\OAuthClient;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_token_with_the_scope_can_read(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Visible']);
        Passport::actingAs($user, ['pages:read']);

        $this->withToken('test')->getJson(route('api.v1.pages.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Visible');
    }

    public function test_partner_token_without_the_write_scope_is_refused(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Passport::actingAs($user, ['pages:read']);

        $this->withToken('test')->deleteJson(route('api.v1.pages.destroy', $page))
            ->assertForbidden()
            ->assertJsonPath('required_scope', 'pages:write');

        $this->assertNotSoftDeleted($page);
    }

    public function test_partner_token_with_the_write_scope_can_write(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Passport::actingAs($user, ['pages:write']);

        $this->withToken('test')->deleteJson(route('api.v1.pages.destroy', $page))->assertNoContent();

        $this->assertSoftDeleted($page);
    }

    public function test_admin_scopes_do_not_grant_admin_rights_to_non_admins(): void
    {
        Passport::actingAs(User::factory()->create(), ['admin:read']);

        $this->withToken('test')->getJson(route('api.v1.admin.users.index'))->assertForbidden();
    }

    public function test_a_bearer_request_never_falls_back_to_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('api.v1.me'))->assertOk();

        $this->actingAs($user)->withToken('not-a-real-token')->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_session_users_are_not_scoped(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson(route('api.v1.pages.destroy', $page))->assertNoContent();
    }

    public function test_the_scope_is_checked_before_route_model_binding(): void
    {
        Passport::actingAs(User::factory()->create(), ['spaces:read']);

        $this->withToken('test')->getJson(route('api.v1.pages.show', 999999))
            ->assertForbidden()
            ->assertHeader('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="pages:read"');
    }

    public function test_session_requests_run_csrf_protection_and_bearer_requests_do_not(): void
    {
        // Laravel skips CSRF while unit testing, so swap in a middleware that always refuses.
        $this->app->bind(PreventRequestForgery::class, fn () => new class
        {
            public function handle(): Response
            {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }
        });

        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();

        $this->actingAs($user)->deleteJson(route('api.v1.pages.destroy', $page))->assertStatus(419);
        $this->assertNotSoftDeleted($page);

        Passport::actingAs($user, ['pages:write']);
        $this->withToken('test')->deleteJson(route('api.v1.pages.destroy', $page))->assertNoContent();
    }

    public function test_writes_made_with_a_token_are_audited_with_the_oauth_client(): void
    {
        $client = new OAuthClient;
        $client->forceFill([
            'name' => 'Partner app',
            'redirect_uris' => ['https://partner.example/callback'],
            'grant_types' => ['authorization_code'],
            'revoked' => false,
            'scopes' => ['pages:write'],
        ])->save();

        $user = User::factory()->create();
        $page = Page::factory()->for($user)->create();
        Passport::actingAs($user, ['pages:write'], 'api', $client);

        $this->withToken('test')->deleteJson(route('api.v1.pages.destroy', $page))->assertNoContent();

        $this->assertSame('api:'.$client->getKey(), AuditLog::query()->where('event', 'page.deleted')->latest('id')->firstOrFail()->metadata['via']);
    }

    public function test_the_rate_limit_is_separate_per_client_for_the_same_user(): void
    {
        $limiter = RateLimiter::limiter('api');
        $user = User::factory()->create();

        $session = Request::create('/api/v1/me');
        $session->setUserResolver(fn () => $user);
        $sessionKey = $limiter($session)->key;

        $client = new OAuthClient;
        $client->forceFill([
            'name' => 'Partner app',
            'redirect_uris' => ['https://partner.example/callback'],
            'grant_types' => ['authorization_code'],
            'revoked' => false,
            'scopes' => ['profile:read'],
        ])->save();
        $tokenUser = User::find($user->id);
        Passport::actingAs($tokenUser, ['profile:read'], 'api', $client);
        $token = Request::create('/api/v1/me');
        $token->setUserResolver(fn () => $tokenUser);

        $this->assertNotSame($sessionKey, $limiter($token)->key);
    }
}
