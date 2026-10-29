<?php

namespace Tests\Feature\Mcp;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpTransportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['Accept' => 'application/json, text/event-stream'];

    /**
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ];
    }

    public function test_initialize_negotiates_protocol_version_2025_11_25(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', $this->initialize(), self::HEADERS)
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-11-25')
            ->assertJsonPath('result.serverInfo.name', 'Corpus');
    }

    public function test_tools_list_returns_the_corpus_tools(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $names = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], self::HEADERS + ['MCP-Protocol-Version' => '2025-11-25'])
            ->assertOk()
            ->json('result.tools'))->pluck('name');

        $this->assertTrue($names->contains('fetch'), $names->implode(', '));
        $this->assertCount(11, $names);
    }

    public function test_list_spaces_reports_personal_space_as_not_updatable(): void
    {
        $admin = User::factory()->admin()->create();
        Passport::actingAs($admin, ['mcp:use']);

        $text = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'list_spaces', 'arguments' => (object) []],
        ], self::HEADERS + ['MCP-Protocol-Version' => '2025-11-25'])
            ->assertOk()
            ->json('result.content.0.text');

        $personal = collect(json_decode($text, true))->firstWhere('id', $admin->personalSpace->id);

        $this->assertFalse($personal['can_update']);
        $this->assertFalse($personal['can_update_visibility']);
        $this->assertFalse($personal['can_manage']);
        $this->assertFalse($personal['can_delete']);
    }

    public function test_notification_is_accepted_with_202_and_no_body(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], self::HEADERS + ['MCP-Protocol-Version' => '2025-11-25'])
            ->assertStatus(202)
            ->assertNoContent(202);
    }

    public function test_unsupported_protocol_version_header_returns_400(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], self::HEADERS + ['MCP-Protocol-Version' => '1999-01-01'])
            ->assertStatus(400);
    }

    public function test_get_returns_405_because_the_server_offers_no_sse_stream(): void
    {
        $this->get('/mcp', ['Accept' => 'text/event-stream'])->assertStatus(405);
    }

    public function test_request_from_a_foreign_origin_is_rejected_with_403(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', $this->initialize(), self::HEADERS + ['Origin' => 'https://evil.example'])
            ->assertForbidden();
    }

    public function test_request_from_the_app_origin_or_without_origin_is_allowed(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', $this->initialize(), self::HEADERS + ['Origin' => config('app.url')])->assertOk();
        $this->postJson('/mcp', $this->initialize(), self::HEADERS)->assertOk();
    }

    public function test_configured_extra_origins_are_allowed(): void
    {
        config(['mcp.allowed_origins' => ['https://claude.ai']]);
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        $this->postJson('/mcp', $this->initialize(), self::HEADERS + ['Origin' => 'https://claude.ai'])->assertOk();
    }

    public function test_access_token_in_the_query_string_is_not_accepted(): void
    {
        $this->postJson('/mcp?access_token=abc', $this->initialize(), self::HEADERS)->assertUnauthorized();
    }

    public function test_writes_made_over_mcp_are_audit_logged_as_mcp(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['mcp:use']);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'create_page', 'arguments' => ['title' => 'From an agent']],
        ], self::HEADERS + ['MCP-Protocol-Version' => '2025-11-25'])->assertOk();

        $this->assertDatabaseHas('pages', ['title' => 'From an agent']);
        $this->assertTrue(AuditLog::where('actor_id', $user->id)->where('metadata->via', 'mcp')->exists());
    }
}
