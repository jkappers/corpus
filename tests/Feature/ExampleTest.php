<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_requires_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_old_cross_space_listings_redirect_home(): void
    {
        $user = User::factory()->create();

        foreach (['/pages', '/boards'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect('/');
        }
    }
}
