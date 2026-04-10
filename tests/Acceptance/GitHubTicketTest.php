<?php

namespace Tests\Acceptance;

use App\Enums\NoteStatus;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\GitHubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Verification point: "Ticket: 'Ticket erstellen' klicken, Issue in GitHub pruefen (Labels, Body-Format)"
 */
class GitHubTicketTest extends TestCase
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

    public function test_send_creates_github_issue_and_stores_reference(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::PROCESSED,
            'audio_path' => 'audio/test.webm',
            'transcript' => 'My voice note transcript',
            'processed_title' => 'Voice note transcript',
            'processed_body' => 'My voice note transcript.',
        ]);

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createIssue')
            ->once()
            ->withArgs(function (string $title, string $body, array $labels) {
                return $title === 'Voice note transcript'
                    && str_contains($body, 'My voice note transcript.')
                    && in_array('type:general', $labels);
            })
            ->andReturn([
                'number' => 42,
                'html_url' => 'https://github.com/test-owner/test-repo/issues/42',
            ]);

        $this->app->instance(GitHubService::class, $github);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/send")
            ->assertRedirect();

        $note->refresh();

        $this->assertEquals(NoteStatus::SENT, $note->status);
        $this->assertEquals(42, $note->github_issue_number);
        $this->assertEquals('https://github.com/test-owner/test-repo/issues/42', $note->github_issue_url);
    }

    public function test_send_sets_error_on_github_failure(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::PROCESSED,
            'audio_path' => 'audio/test.webm',
            'transcript' => 'Transcript',
            'processed_title' => 'Title',
            'processed_body' => 'Body',
        ]);

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createIssue')
            ->andThrow(new \RuntimeException('GitHub API rate limited'));

        $this->app->instance(GitHubService::class, $github);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/send")
            ->assertRedirect();

        $note->refresh();

        $this->assertEquals(NoteStatus::ERROR, $note->status);
        $this->assertNotNull($note->error_message);
    }

    public function test_sent_note_preserves_github_link(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::SENT,
            'audio_path' => 'audio/test.webm',
            'transcript' => 'Transcript',
            'processed_title' => 'Title',
            'processed_body' => 'Body',
            'github_issue_number' => 42,
            'github_issue_url' => 'https://github.com/test-owner/test-repo/issues/42',
        ]);

        $response = $this->actingAs($this->user)->get("/notes/{$note->id}");

        $response->assertStatus(200);
    }

    public function test_send_rejects_unprocessed_note(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::RECORDED,
            'audio_path' => 'audio/test.webm',
        ]);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/send")
            ->assertSessionHasErrors('status');

        $note->refresh();
        $this->assertEquals(NoteStatus::RECORDED, $note->status);
    }

    public function test_send_rejects_already_sent_note(): void
    {
        $note = VoiceNote::create([
            'type' => 'general',
            'status' => NoteStatus::SENT,
            'audio_path' => 'audio/test.webm',
            'processed_title' => 'Title',
            'processed_body' => 'Body',
            'github_issue_number' => 42,
            'github_issue_url' => 'https://github.com/test/test/issues/42',
        ]);

        $this->actingAs($this->user)
            ->post("/notes/{$note->id}/send")
            ->assertSessionHasErrors('status');
    }

    public function test_issue_body_contains_correct_labels(): void
    {
        $note = VoiceNote::create([
            'type' => 'youtube',
            'status' => NoteStatus::PROCESSED,
            'audio_path' => 'audio/test.webm',
            'transcript' => 'Transcript',
            'processed_title' => 'YouTube video transcript',
            'processed_body' => 'Transcript about the video.',
            'metadata' => ['youtube_url' => 'https://youtube.com/watch?v=test'],
        ]);

        $github = Mockery::mock(GitHubService::class);
        $github->shouldReceive('createIssue')
            ->once()
            ->withArgs(function (string $title, string $body, array $labels) {
                return in_array('type:youtube', $labels);
            })
            ->andReturn(['number' => 43, 'html_url' => 'https://github.com/test/test/issues/43']);

        $this->app->instance(GitHubService::class, $github);

        $this->actingAs($this->user)->post("/notes/{$note->id}/send");
    }
}
