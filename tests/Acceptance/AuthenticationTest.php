<?php

namespace Tests\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification point: "Browser-Auth: Ohne Key → abgewiesen; mit Key ohne TOTP → abgewiesen; mit beidem → Zugang"
 *
 * NFR-15a-01: Two-Factor Browser Authentication
 * NFR-15a-02: Login Rate Limiting and Lockout
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => config('herold.admin_email'),
            'api_key_hash' => hash('sha256', 'test-api-key-for-testing'),
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_confirmed_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_wrong_api_key_is_rejected(): void
    {
        $this->post('/login/key', ['api_key' => 'wrong-key'])
            ->assertSessionHasErrors();
    }

    public function test_correct_api_key_advances_to_totp_step(): void
    {
        $response = $this->post('/login/key', ['api_key' => 'test-api-key-for-testing']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('auth.key_verified', true);
    }

    public function test_totp_without_key_verification_is_rejected(): void
    {
        $this->post('/login/totp', ['totp_code' => '123456'])
            ->assertRedirect('/login');
    }

    public function test_wrong_totp_code_is_rejected(): void
    {
        $this->withSession(['auth.key_verified' => true, 'auth.user_id' => $this->user->id])
            ->post('/login/totp', ['totp_code' => '000000'])
            ->assertSessionHasErrors();
    }

    public function test_login_rate_limiting_on_key_verification(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login/key', ['api_key' => 'wrong-key']);
        }

        $this->post('/login/key', ['api_key' => 'wrong-key'])
            ->assertStatus(429);
    }

    public function test_login_rate_limiting_on_totp_verification(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['auth.key_verified' => true, 'auth.user_id' => $this->user->id])
                ->post('/login/totp', ['totp_code' => '000000']);
        }

        $this->withSession(['auth.key_verified' => true, 'auth.user_id' => $this->user->id])
            ->post('/login/totp', ['totp_code' => '000000'])
            ->assertStatus(429);
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get('/')
            ->assertStatus(200);
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs($this->user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->get('/')->assertRedirect('/login');
    }

    public function test_totp_verify_rejects_user_with_unconfirmed_totp(): void
    {
        $this->user->update(['totp_confirmed_at' => null]);

        $this->withSession(['auth.key_verified' => true, 'auth.user_id' => $this->user->id])
            ->post('/login/totp', ['totp_code' => '123456'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_protected_routes_require_authentication(): void
    {
        $routes = [
            ['GET', '/'],
            ['GET', '/notes'],
            ['GET', '/notes/create'],
            ['GET', '/settings'],
            ['GET', '/types'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->{strtolower($method)}($uri)->assertRedirect('/login');
        }
    }
}
