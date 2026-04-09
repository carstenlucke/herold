<?php

namespace Tests\Acceptance;

use App\Enums\NoteStatus;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Verification point: "Transkription + Vorverarbeitung: Audio hochladen, 'Verarbeiten' klicken,
 * Loading-Indikator pruefen, strukturiertes Ergebnis pruefen"
 *
 * NFR-12a-01: Synchronous Processing
 * NFR-12d-01: Synchronous Error Handling
 */
class ProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create([
            'api_key_hash' => hash('sha256', 'test-api-key-for-testing'),
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'totp_confirmed_at' => now(),
        ]);
    }

    public function test_process_transcribes_and_preprocesses_note(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->once()
            ->andReturn('This is a test transcription of my voice note.');
        $aiService->shouldReceive('chat')
            ->once()
            ->andReturn([
                'title' => 'Test Task Title',
                'body' => '## Description\n\nThis is the processed body.',
            ]);

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();

        $this->assertEquals(NoteStatus::PROCESSED, $note->status);
        $this->assertEquals('This is a test transcription of my voice note.', $note->transcript);
        $this->assertEquals('Test Task Title', $note->processed_title);
        $this->assertNotNull($note->processed_body);
    }

    public function test_process_sets_error_status_on_transcription_failure(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->once()
            ->andThrow(new \RuntimeException('OpenAI API unavailable'));

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();

        $this->assertEquals(NoteStatus::ERROR, $note->status);
        $this->assertNotNull($note->error_message);
    }

    public function test_process_preserves_audio_on_error(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->andThrow(new \RuntimeException('API error'));

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process");

        Storage::assertExists('audio/test.webm');
        $this->assertDatabaseHas('voice_notes', ['id' => $note->id]);
    }

    public function test_error_note_can_be_retried(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::ERROR,
            'audio_path' => 'audio/test.webm',
            'error_message' => 'Previous failure',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->andReturn('Retried transcription');
        $aiService->shouldReceive('chat')
            ->andReturn(['title' => 'Retried Title', 'body' => 'Retried body']);

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();
        $this->assertEquals(NoteStatus::PROCESSED, $note->status);
        $this->assertNull($note->error_message);
    }

    public function test_note_fields_are_editable_after_processing(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::PROCESSED,
            'audio_path' => 'audio/test.webm',
            'transcript' => 'Original transcript',
            'processed_title' => 'Original Title',
            'processed_body' => 'Original body',
        ]);

        $this->actingAs($this->user)
            ->put("/notes/{$note->id}", [
                'transcript' => 'Edited transcript',
                'processed_title' => 'Edited Title',
                'processed_body' => 'Edited body',
            ])
            ->assertRedirect();

        $note->refresh();
        $this->assertEquals('Edited transcript', $note->transcript);
        $this->assertEquals('Edited Title', $note->processed_title);
        $this->assertEquals('Edited body', $note->processed_body);
    }
}
