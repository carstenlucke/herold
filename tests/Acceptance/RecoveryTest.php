<?php

namespace Tests\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verification point: "Recovery: '.herold-recovery' per FTP in storage/app/private/ hochladen
 * → /recovery erreichbar → Auth zuruecksetzen → Datei automatisch geloescht → /recovery wieder 404"
 *
 * NFR-14a-02: Auth Recovery via FTP
 * NFR-15a-04: Recovery Token Expiry
 */
class RecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up recovery file from previous tests to ensure isolation
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @unlink($recoveryPath);

        $this->user = User::factory()->create([
            'email' => config('herold.admin_email'),
            'api_key_hash' => hash('sha256', 'test-api-key-for-testing'),
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_confirmed_at' => now(),
        ]);
    }

    public function test_recovery_returns_404_without_file(): void
    {
        $this->get('/recovery')->assertStatus(404);
    }

    public function test_recovery_shows_form_when_file_exists(): void
    {
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, 'test-recovery-token-abc123');

        $this->get('/recovery')->assertStatus(200);

        @unlink($recoveryPath);
    }

    public function test_recovery_resets_auth_with_correct_token(): void
    {
        $token = 'test-recovery-token-abc123';
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, $token);

        $response = $this->post('/recovery', ['token' => $token]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/RecoverySuccess')
            ->has('apiKey')
        );
        $this->assertFileDoesNotExist($recoveryPath);
    }

    public function test_recovery_returns_404_with_wrong_token(): void
    {
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, 'correct-token');

        $response = $this->post('/recovery', ['token' => 'wrong-token']);

        $response->assertStatus(404);
        // File should still exist (not consumed on wrong token)
        $this->assertFileExists($recoveryPath);

        @unlink($recoveryPath);
    }

    public function test_recovery_returns_404_for_expired_token(): void
    {
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, 'expired-token');
        // Set file time to 61 minutes ago
        touch($recoveryPath, time() - 3660);

        $this->get('/recovery')->assertStatus(404);

        @unlink($recoveryPath);
    }

    public function test_recovery_file_is_deleted_after_successful_reset(): void
    {
        $token = 'consume-once-token';
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, $token);

        $this->post('/recovery', ['token' => $token]);

        $this->assertFileDoesNotExist($recoveryPath);

        // Subsequent access returns 404
        $this->get('/recovery')->assertStatus(404);
    }

    public function test_recovery_rate_limiting(): void
    {
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, 'rate-limit-token');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/recovery', ['token' => 'wrong']);
        }

        $this->post('/recovery', ['token' => 'wrong'])->assertStatus(429);

        @unlink($recoveryPath);
    }

    public function test_all_error_responses_are_uniform_404(): void
    {
        // No file → 404
        $noFile = $this->get('/recovery');
        $this->assertEquals(404, $noFile->status());

        // Expired file → 404
        $recoveryPath = storage_path('app/private/.herold-recovery');
        @mkdir(dirname($recoveryPath), 0755, true);
        file_put_contents($recoveryPath, 'token');
        touch($recoveryPath, time() - 3660);
        $expired = $this->get('/recovery');
        $this->assertEquals(404, $expired->status());

        // Wrong token → 404
        file_put_contents($recoveryPath, 'correct');
        touch($recoveryPath); // reset mtime
        $wrong = $this->post('/recovery', ['token' => 'incorrect']);
        $this->assertEquals(404, $wrong->status());

        @unlink($recoveryPath);
    }
}
