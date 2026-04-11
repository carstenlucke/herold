<?php

namespace Tests\Acceptance;

use App\Enums\NoteStatus;
use App\Models\User;
use App\Models\VoiceNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verification point: "Audio-Aufnahme: Im Browser aufnehmen, pruefen ob Datei in storage landet"
 *
 * NFR-15a-03: Audio Upload Validation (max 25MB, MIME types, rate limit)
 */
class AudioUploadTest extends TestCase
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

    public function test_audio_upload_creates_voice_note_with_recorded_status(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('voice_notes', [
            'type' => 'general',
            'status' => NoteStatus::RECORDED->value,
        ]);

        $note = VoiceNote::first();
        Storage::assertExists($note->audio_path);
    }

    public function test_audio_upload_rejects_files_exceeding_25mb(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 26000, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ])
            ->assertSessionHasErrors('audio');
    }

    public function test_audio_upload_rejects_invalid_mime_types(): void
    {
        $audio = UploadedFile::fake()->create('recording.mp3', 1024, 'audio/mpeg');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ])
            ->assertSessionHasErrors('audio');
    }

    public function test_audio_upload_rejects_invalid_type(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'nonexistent',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_youtube_type_requires_url(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'youtube',
            ])
            ->assertSessionHasErrors('metadata.youtube_url');
    }

    public function test_youtube_type_accepts_valid_url(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'youtube',
                'metadata' => ['youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ]);

        $response->assertRedirect();

        $note = VoiceNote::first();
        $this->assertEquals('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $note->metadata['youtube_url']);
    }

    public function test_ogg_upload_stores_with_ogg_extension(): void
    {
        $audio = UploadedFile::fake()->create('recording.ogg', 1024, 'audio/ogg');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ]);

        $note = VoiceNote::first();
        $this->assertStringEndsWith('.ogg', $note->audio_path);
        Storage::assertExists($note->audio_path);
    }

    public function test_mp4_upload_stores_with_m4a_extension(): void
    {
        $audio = UploadedFile::fake()->create('recording.m4a', 1024, 'audio/mp4');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ]);

        $note = VoiceNote::first();
        $this->assertStringEndsWith('.m4a', $note->audio_path);
        Storage::assertExists($note->audio_path);
    }

    public function test_audio_upload_rate_limiting(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $audio = UploadedFile::fake()->create("recording{$i}.webm", 100, 'audio/webm');
            $this->actingAs($this->user)->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ]);
        }

        $audio = UploadedFile::fake()->create('recording_extra.webm', 100, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
            ])
            ->assertStatus(429);
    }

    public function test_obsidian_type_accepts_optional_vault(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'obsidian',
                'metadata' => ['vault' => 'Work'],
            ]);

        $response->assertRedirect();

        $note = VoiceNote::latest()->first();
        $this->assertEquals('Work', $note->metadata['vault']);
    }

    public function test_obsidian_type_works_without_vault(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'obsidian',
            ]);

        $response->assertRedirect();
    }

    public function test_todo_type_accepts_valid_deadline(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'todo',
                'metadata' => ['deadline' => '2026-04-20'],
            ]);

        $response->assertRedirect();

        $note = VoiceNote::latest()->first();
        $this->assertEquals('2026-04-20', $note->metadata['deadline']);
    }

    public function test_todo_type_rejects_invalid_deadline_format(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'todo',
                'metadata' => ['deadline' => 'next friday'],
            ])
            ->assertSessionHasErrors('metadata.deadline');
    }

    public function test_unknown_metadata_keys_are_stripped(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)
            ->post('/notes', [
                'audio' => $audio,
                'type' => 'general',
                'metadata' => ['rogue_key' => 'should be stripped'],
            ]);

        $note = VoiceNote::latest()->first();
        $this->assertNull($note->metadata);
    }

    public function test_voice_note_can_be_deleted(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 1024, 'audio/webm');

        $this->actingAs($this->user)->post('/notes', [
            'audio' => $audio,
            'type' => 'general',
        ]);

        $note = VoiceNote::first();

        $this->actingAs($this->user)
            ->delete("/notes/{$note->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('voice_notes', ['id' => $note->id]);
    }
}
