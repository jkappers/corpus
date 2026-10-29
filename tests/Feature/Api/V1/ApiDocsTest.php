<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_openapi_document_is_for_signed_in_users_only(): void
    {
        $this->getJson('/docs/api.json')->assertForbidden();
        $this->get('/docs/api')->assertForbidden();
    }

    public function test_the_openapi_document_describes_v1_with_bearer_auth(): void
    {
        $document = $this->actingAs(User::factory()->create())->getJson('/docs/api.json')->assertOk()->json();

        $this->assertArrayHasKey('/v1/pages', $document['paths']);
        $this->assertArrayHasKey('/v1/me/tokens', $document['paths']);
        $this->assertSame('bearer', $document['components']['securitySchemes']['http']['scheme']);
        $this->assertArrayNotHasKey('/oauth/token', $document['paths']);
    }
}
