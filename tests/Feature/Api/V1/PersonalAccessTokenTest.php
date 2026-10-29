<?php

namespace Tests\Feature\Api\V1;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $scopes
     */
    private function issue(User $user, array $scopes, string $name = 'CI'): string
    {
        $token = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.v1.tokens.store'), ['name' => $name, 'scopes' => $scopes])
            ->assertCreated()
            ->json('meta.token');

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function test_a_user_issues_a_token_that_works_within_its_scopes(): void
    {
        $user = User::factory()->create();
        Page::factory()->for($user)->create(['title' => 'Mine']);

        $token = $this->issue($user, ['pages:read']);

        $this->withToken($token)->getJson(route('api.v1.pages.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Mine');

        $this->withToken($token)->postJson(route('api.v1.pages.store'))->assertForbidden();
    }

    public function test_a_personal_access_token_cannot_reach_the_mcp_endpoint(): void
    {
        $token = $this->issue(User::factory()->create(), ['pages:read', 'pages:write']);

        $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])
            ->assertForbidden();
    }

    public function test_only_administrators_may_grant_admin_scopes(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('api.v1.tokens.store'), ['name' => 'Sneaky', 'scopes' => ['admin:write']])
            ->assertJsonValidationErrors('scopes.0');

        $admin = User::factory()->admin()->create();
        $token = $this->issue($admin, ['admin:read']);

        $this->withToken($token)->getJson(route('api.v1.admin.users.index'))->assertOk();
    }

    public function test_creating_a_token_requires_a_confirmed_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.v1.tokens.store'), ['name' => 'CI', 'scopes' => ['pages:read']])
            ->assertStatus(423);
    }

    public function test_tokens_cannot_manage_tokens(): void
    {
        Passport::actingAs(User::factory()->create(), ['pages:read']);

        $this->withToken('test')->getJson(route('api.v1.tokens.index'))->assertForbidden();
    }

    public function test_tokens_are_listed_and_revoked_by_their_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->issue($owner, ['pages:read'], 'Laptop');

        $id = $this->actingAs($owner)->getJson(route('api.v1.tokens.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Laptop')
            ->assertJsonPath('data.0.scopes', ['pages:read'])
            ->json('data.0.id');

        $this->actingAs($other)->deleteJson(route('api.v1.tokens.destroy', $id))->assertNotFound();

        $this->actingAs($owner)->deleteJson(route('api.v1.tokens.destroy', $id))->assertNoContent();
        $this->getJson(route('api.v1.tokens.index'))->assertJsonCount(0, 'data');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(route('api.v1.pages.index'))->assertUnauthorized();
    }
}
