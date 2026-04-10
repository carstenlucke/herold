<?php

namespace Tests\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-15b-01: No API Keys in Frontend
 * NFR-15b-02: No Preprocessing Prompts in API Responses
 */
class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'api_key_hash' => hash('sha256', 'test-api-key-for-testing'),
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_confirmed_at' => now(),
        ]);
    }

    public function test_types_response_contains_no_api_keys(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        $json = $response->content();

        $this->assertStringNotContainsString('OPENAI_API_KEY', $json);
        $this->assertStringNotContainsString('GITHUB_TOKEN', $json);
        $this->assertStringNotContainsString('HEROLD_GITHUB_TOKEN', $json);
        $this->assertStringNotContainsString('HEROLD_API_KEY', $json);
        $this->assertStringNotContainsString(config('herold.openai.api_key') ?? '', $json);
    }

    public function test_types_response_contains_no_preprocessing_prompts(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        $json = $response->content();

        $this->assertStringNotContainsString('preprocessing_prompt', $json);
    }

    public function test_dashboard_page_contains_no_secrets(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $content = $response->content();

        $this->assertStringNotContainsString(config('herold.api_key') ?? '', $content);
        $this->assertStringNotContainsString(config('herold.github.token') ?? '', $content);
        $this->assertStringNotContainsString(config('herold.openai.api_key') ?? '', $content);
    }

    public function test_settings_page_does_not_expose_tokens(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');

        $content = $response->content();

        $this->assertStringNotContainsString(config('herold.github.token') ?? '', $content);
        $this->assertStringNotContainsString(config('herold.openai.api_key') ?? '', $content);
    }
}
