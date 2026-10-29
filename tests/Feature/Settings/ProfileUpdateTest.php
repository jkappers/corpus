<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\ApiScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(route('api.v1.me.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response->assertOk();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(route('api.v1.me.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response->assertOk();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_unverified_users_can_correct_their_email_address(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->patchJson(route('api.v1.me.update'), ['name' => $user->name, 'email' => 'fixed@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'fixed@example.com');
    }

    public function test_profile_and_password_cannot_be_changed_with_an_access_token(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ApiScopes::all());

        $this->withToken('test')
            ->patchJson(route('api.v1.me.update'), ['name' => 'Hijacked', 'email' => $user->email])
            ->assertForbidden();
        $this->withToken('test')
            ->putJson(route('api.v1.me.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $user->refresh()->name);
    }

    public function test_user_cannot_delete_their_own_account()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
