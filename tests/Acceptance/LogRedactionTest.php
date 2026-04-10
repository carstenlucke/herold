<?php

namespace Tests\Acceptance;

use App\Logging\SecretRedactionProcessor;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Verification point: "Log-Redaction: storage/logs/ pruefen → keine Secrets,
 * keine Bearer-/Session-Token, keine Transcript-Inhalte"
 *
 * NFR-15b-03: Secret Redaction in Logs
 */
class LogRedactionTest extends TestCase
{
    protected SecretRedactionProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:testappkey1234567890==',
            'herold.api_key' => 'secret-herold-api-key',
            'herold.github.token' => 'ghp_secretgithubtoken123',
            'herold.openai.api_key' => 'sk-secretopenaikey123',
        ]);

        $this->processor = new SecretRedactionProcessor;
    }

    public function test_app_key_is_redacted(): void
    {
        $record = $this->makeRecord('Config loaded with key base64:testappkey1234567890==');
        $result = ($this->processor)($record);

        $this->assertStringNotContainsString('base64:testappkey1234567890==', $result->message);
        $this->assertStringContainsString('[REDACTED]', $result->message);
    }

    public function test_herold_api_key_is_redacted(): void
    {
        $record = $this->makeRecord('Login attempt with key secret-herold-api-key');
        $result = ($this->processor)($record);

        $this->assertStringNotContainsString('secret-herold-api-key', $result->message);
        $this->assertStringContainsString('[REDACTED]', $result->message);
    }

    public function test_github_token_is_redacted(): void
    {
        $record = $this->makeRecord('GitHub request with token ghp_secretgithubtoken123');
        $result = ($this->processor)($record);

        $this->assertStringNotContainsString('ghp_secretgithubtoken123', $result->message);
        $this->assertStringContainsString('[REDACTED]', $result->message);
    }

    public function test_openai_key_is_redacted(): void
    {
        $record = $this->makeRecord('OpenAI call with key sk-secretopenaikey123');
        $result = ($this->processor)($record);

        $this->assertStringNotContainsString('sk-secretopenaikey123', $result->message);
        $this->assertStringContainsString('[REDACTED]', $result->message);
    }

    public function test_bearer_token_is_redacted(): void
    {
        $record = $this->makeRecord('Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9');
        $result = ($this->processor)($record);

        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $result->message);
        $this->assertStringContainsString('[REDACTED]', $result->message);
    }

    public function test_context_values_are_redacted(): void
    {
        $record = $this->makeRecord('API call', [
            'authorization' => 'Bearer secret-token',
            'api_key' => 'sk-secretopenaikey123',
        ]);

        $result = ($this->processor)($record);

        $this->assertEquals('[REDACTED]', $result->context['authorization']);
        $this->assertEquals('[REDACTED]', $result->context['api_key']);
    }

    public function test_normal_messages_are_not_modified(): void
    {
        $record = $this->makeRecord('Voice note abc123 processed successfully');
        $result = ($this->processor)($record);

        $this->assertEquals('Voice note abc123 processed successfully', $result->message);
    }

    public function test_session_tokens_are_redacted_in_context(): void
    {
        $record = $this->makeRecord('Session event', [
            'session_id' => 'abc123def456session',
            'cookie' => 'laravel_session=abc123secretcookie',
        ]);

        $result = ($this->processor)($record);

        $this->assertEquals('[REDACTED]', $result->context['session_id']);
        $this->assertEquals('[REDACTED]', $result->context['cookie']);
    }

    public function test_processor_is_registered_on_single_channel(): void
    {
        $logger = Log::channel('single')->getLogger();
        $processors = $logger->getProcessors();

        $this->assertNotEmpty(array_filter(
            $processors,
            fn ($p) => $p instanceof SecretRedactionProcessor
        ));
    }

    private function makeRecord(string $message, array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: $message,
            context: $context,
        );
    }
}
