<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JarvisCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ── Health Check ───────────────────────────────────────────────────────────

    public function test_health_endpoint_is_accessible(): void
    {
        $response = $this->getJson('/api/jarvis/health');
        $response->assertOk()
                 ->assertJson(['status' => 'online']);
    }

    // ── Unauthenticated ────────────────────────────────────────────────────────

    public function test_command_requires_authentication(): void
    {
        $response = $this->postJson('/api/jarvis/command', ['command' => 'hello jarvis']);
        $response->assertUnauthorized();
    }

    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/jarvis/history');
        $response->assertUnauthorized();
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function test_command_requires_command_field(): void
    {
        $response = $this->postJson('/api/jarvis/command', [], $this->authHeaders());
        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['command']);
    }

    public function test_command_rejects_too_long_input(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => str_repeat('a', 501),
        ], $this->authHeaders());
        $response->assertUnprocessable();
    }

    // ── Command Processing ─────────────────────────────────────────────────────

    public function test_processes_hello_jarvis(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => 'hello jarvis',
        ], $this->authHeaders());

        $response->assertOk()
                 ->assertJsonStructure(['success', 'intent', 'message', 'action'])
                 ->assertJson(['intent' => 'greet', 'success' => true]);
    }

    public function test_processes_open_instagram(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => 'open instagram',
        ], $this->authHeaders());

        $response->assertOk()
                 ->assertJson(['success' => true])
                 ->assertJsonFragment(['type' => 'open_url']);
    }

    public function test_processes_instagram_open_karo(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => 'instagram open karo',
        ], $this->authHeaders());

        $response->assertOk()
                 ->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertStringContainsString('instagram.com', $data['action']['url'] ?? '');
    }

    public function test_processes_youtube_new_tab(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => 'youtube new tab mein kholo',
        ], $this->authHeaders());

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['action']['new_tab'] ?? false);
        $this->assertStringContainsString('youtube.com', $data['action']['url'] ?? '');
    }

    public function test_processes_google_search(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => 'google par laravel search karo',
        ], $this->authHeaders());

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('search_web', $data['intent']);
        $this->assertStringContainsString('laravel', $data['action']['url'] ?? '');
    }

    // ── Security ───────────────────────────────────────────────────────────────

    public function test_strips_html_tags_from_command(): void
    {
        $response = $this->postJson('/api/jarvis/command', [
            'command' => '<script>alert(1)</script>open instagram',
        ], $this->authHeaders());

        // Should process without error — HTML stripped
        $response->assertOk();
    }

    // ── History ────────────────────────────────────────────────────────────────

    public function test_history_is_recorded_after_command(): void
    {
        $this->postJson('/api/jarvis/command', [
            'command' => 'hello jarvis',
        ], $this->authHeaders());

        $response = $this->getJson('/api/jarvis/history', $this->authHeaders());
        $response->assertOk()
                 ->assertJsonPath('meta.total', 1);
    }

    public function test_can_clear_history(): void
    {
        $this->postJson('/api/jarvis/command', ['command' => 'hello jarvis'], $this->authHeaders());

        $this->deleteJson('/api/jarvis/history', [], $this->authHeaders())
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->getJson('/api/jarvis/history', $this->authHeaders())
             ->assertJsonPath('meta.total', 0);
    }

    // ── Settings ───────────────────────────────────────────────────────────────

    public function test_can_update_settings(): void
    {
        $this->putJson('/api/jarvis/settings', [
            'key'   => 'theme',
            'value' => 'dark',
        ], $this->authHeaders())->assertOk()->assertJson(['success' => true]);
    }

    public function test_rejects_invalid_setting_key(): void
    {
        $this->putJson('/api/jarvis/settings', [
            'key'   => 'malicious_key',
            'value' => 'evil_value',
        ], $this->authHeaders())->assertUnprocessable();
    }
}
