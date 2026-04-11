<?php

namespace Tests\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification point for Security Headers (from spec Phase 7 / NFR)
 *
 * Validates: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
 */
class SecurityHeadersTest extends TestCase
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

    public function test_x_frame_options_is_deny(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_x_content_type_options_is_nosniff(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_referrer_policy_is_set(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_restricts_camera_and_microphone(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(self)');
    }

    public function test_security_headers_on_authenticated_routes(): void
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(self)');
    }

    public function test_security_headers_on_json_responses(): void
    {
        $response = $this->actingAs($this->user)->getJson('/types');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
