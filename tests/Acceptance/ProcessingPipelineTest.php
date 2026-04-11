<?php

namespace Tests\Acceptance;

use App\Enums\NoteStatus;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                'title' => 'Test voice note transcription',
                'body' => 'This is a test transcription of my voice note.',
            ]);

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();

        $this->assertEquals(NoteStatus::PROCESSED, $note->status);
        $this->assertEquals('This is a test transcription of my voice note.', $note->transcript);
        $this->assertEquals('Test voice note transcription', $note->processed_title);
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

    public function test_process_extracts_deadline_for_todo_type(): void
    {
        $note = VoiceNote::create([
            'type' => 'todo',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->andReturn('Buy groceries by next Friday.');
        $aiService->shouldReceive('chat')
            ->once()
            ->withArgs(function ($systemPrompt, $userMessage) {
                // Verify current date context is appended for types with needs_current_date_context
                return str_contains($userMessage, 'Current date:');
            })
            ->andReturn([
                'title' => 'Buy groceries',
                'body' => 'Buy groceries by next Friday.',
                'deadline' => '2026-04-17',
            ]);

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();
        $this->assertEquals(NoteStatus::PROCESSED, $note->status);
        $this->assertEquals('2026-04-17', $note->metadata['deadline']);
    }

    public function test_process_extracts_vault_for_obsidian_type(): void
    {
        $note = VoiceNote::create([
            'type' => 'obsidian',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        Storage::put('audio/test.webm', 'fake-audio-content');

        $aiService = Mockery::mock(AIService::class);
        $aiService->shouldReceive('transcribe')
            ->andReturn('Note for my research vault about quantum computing.');
        $aiService->shouldReceive('chat')
            ->once()
            ->withArgs(function ($systemPrompt, $userMessage) {
                // Obsidian has no needs_current_date_context, so no date context
                return ! str_contains($userMessage, 'Current date:');
            })
            ->andReturn([
                'title' => 'Quantum computing research note',
                'body' => 'Note for my research vault about quantum computing.',
                'vault' => 'Research',
            ]);

        $this->app->instance(AIService::class, $aiService);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/process")
            ->assertRedirect();

        $note->refresh();
        $this->assertEquals(NoteStatus::PROCESSED, $note->status);
        $this->assertEquals('Research', $note->metadata['vault']);
    }

    public function test_todo_deadline_is_validated_on_update(): void
    {
        $note = VoiceNote::create([
            'type' => 'todo',
            'status' => NoteStatus::PROCESSED,
            'processed_title' => 'Test',
            'processed_body' => 'Test body',
        ]);

        $this->actingAs($this->user)
            ->put("/notes/{$note->id}", [
                'metadata' => ['deadline' => 'invalid-date'],
            ])
            ->assertSessionHasErrors('metadata.deadline');
    }

    public function test_update_rejects_clearing_required_metadata_field(): void
    {
        $note = VoiceNote::create([
            'type' => 'youtube',
            'status' => NoteStatus::PROCESSED,
            'processed_title' => 'Test',
            'processed_body' => 'Test body',
            'metadata' => ['youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ]);

        $this->actingAs($this->user)
            ->put("/notes/{$note->id}", [
                'processed_title' => 'Updated Title',
                'metadata' => ['youtube_url' => ''],
            ])
            ->assertSessionHasErrors('metadata.youtube_url');

        $note->refresh();
        $this->assertEquals('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $note->metadata['youtube_url']);
    }

    public function test_store_validation_scoped_to_selected_type(): void
    {
        // Add a second type that reuses the field name 'priority' with different rules
        config(['herold.types.type_a' => [
            'label' => 'Type A',
            'icon' => 'mdi-alpha-a',
            'github_label' => 'type:type-a',
            'extra_fields' => [
                ['name' => 'priority', 'type' => 'text', 'required' => true, 'label' => 'Priority'],
            ],
            'preprocessing_prompt' => 'Process type A.',
        ]]);

        config(['herold.types.type_b' => [
            'label' => 'Type B',
            'icon' => 'mdi-alpha-b',
            'github_label' => 'type:type-b',
            'extra_fields' => [
                ['name' => 'priority', 'type' => 'text', 'required' => false, 'label' => 'Priority'],
            ],
            'preprocessing_prompt' => 'Process type B.',
        ]]);

        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        // type_a requires priority — submitting without it should fail
        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'type_a',
            ])
            ->assertSessionHasErrors('metadata.priority');

        // type_b does not require priority — submitting without it should succeed
        $audio = UploadedFile::fake()->create('recording2.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'type_b',
            ])
            ->assertRedirect();
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
