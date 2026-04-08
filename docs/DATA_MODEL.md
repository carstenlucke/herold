# Herold Data Model

Visual diagram: [`datamodel.plantuml`](datamodel.plantuml) (generate PNG with `./scripts/generate-diagrams.sh`).

---

## Domain Entities

### voice_notes

The central entity of Herold. Each row represents one voice note from recording through ticket creation.

| Column | Purpose |
|--------|---------|
| `id` | ULID primary key (time-sortable, no auto-increment) |
| `type` | Message type (`general`, `youtube`, `diary`). Not an enum — validated against `config/herold.php` so new types require only a config entry, no code change. |
| `status` | Processing state, see [NoteStatus](#notestatus). |
| `audio_path` | Relative path in `storage/app/private/audio/`. Set on upload, nullable until then. |
| `transcript` | Raw transcription from OpenAI Whisper. |
| `processed_title` | LLM-generated title for the GitHub issue. Editable by user before sending. |
| `processed_body` | LLM-generated issue body in **Markdown** format. Editable by user before sending. |
| `metadata` | Type-specific JSON data (e.g. `{"youtube_url": "..."}` for youtube type). Schema depends on `type`, defined via `extra_fields` in `config/herold.php`. |
| `github_issue_number` | GitHub issue number after successful creation. |
| `github_issue_url` | Full URL to the created GitHub issue. |
| `error_message` | Error details when a processing step fails. |

### memories

Agent knowledge base. AI agents (Claude Code, OpenCode) store and retrieve learnings, decisions, and context via the REST API.

| Column | Purpose |
|--------|---------|
| `id` | ULID primary key |
| `scope` | Namespace for the memory. See [MemoryScope](#memoryscope). |
| `category` | Classification of the memory. See [MemoryCategory](#memorycategory). |
| `content` | The actual knowledge, free-form text. Has a full-text index for search. |
| `source` | Who created this memory (`claude-code`, `opencode`, `user`, `herold`). |

Indexes: composite `(scope, category)` for filtered queries, full-text on `content` for search.

---

## Enums

### NoteStatus

Tracks progress through the voice-note processing pipeline.

| Value | Meaning | Typical duration |
|-------|---------|-----------------|
| `recorded` | Audio saved, awaiting user action | Until user clicks "Process" |
| `transcribing` | Whisper API call in progress | Seconds |
| `transcribed` | Transcript available, queued for LLM | Milliseconds (auto-chained) |
| `processing` | LLM generating title + body | Seconds |
| `processed` | Result ready for user review | Until user clicks "Create Ticket" |
| `sending` | GitHub API call in progress | Seconds |
| `sent` | GitHub issue created successfully | Final state |
| `error` | A processing step failed | Until retry |

**UI grouping:** Most intermediate states are only active for seconds. The UI groups them into four visible states:

| UI label | NoteStatus values |
|----------|-------------------|
| Aufgenommen | `recorded` |
| Wird verarbeitet... | `transcribing`, `transcribed`, `processing`, `sending` |
| Fertig | `processed` |
| Gesendet | `sent` |
| Fehler | `error` |

The granular states are kept for **error diagnosis** — when a job fails, the status tells you exactly which step failed (Whisper vs. LLM vs. GitHub API), enabling targeted retries.

### MemoryScope

| Value | Meaning |
|-------|---------|
| `global` | Applies across all projects |
| `project:{name}` | Scoped to a specific project (e.g. `project:herold`) |
| `ticket:{number}` | Scoped to a specific GitHub issue number |

### MemoryCategory

| Value | Meaning |
|-------|---------|
| `decision` | Architectural or design decision |
| `learning` | Something learned during implementation |
| `preference` | User or project preference |
| `context` | Background context for understanding |

---

## Laravel Framework Tables

These tables are not Herold domain entities but are required by the Laravel framework. Their schema is defined by Laravel packages and should not be modified.

### users

Standard Laravel `users` table. Herold is a **single-user system** — this table holds exactly one row. Required by Laravel's auth system and Sanctum's token ownership.

Notable columns:
- `remember_token` — Laravel's "Remember Me" cookie token. **Unused** in Herold (we use API-Key + TOTP authentication, not username/password with "Remember Me").

### personal_access_tokens

**Laravel Sanctum** table. Stores hashed Bearer tokens for agent API authentication. Each token has:

- `name` — human-readable label (e.g. "Claude Code on MacBook")
- `token` — SHA-256 hash of the actual token (the plain token is shown only once at creation)
- `abilities` — JSON-encoded list of scopes that control what the agent can do:
  - `memory:read` — read/search memories
  - `memory:write` — create/delete memories
  - `tickets:read` — list tickets
  - `tickets:status` — update ticket status
- `last_used_at` — updated on each API call, visible in Settings UI
- `tokenable_type` / `tokenable_id` — polymorphic relation to `users` (always `App\Models\User` in Herold)

Tokens are managed by the user via the Settings page (create, list, revoke).

### jobs

Standard **Laravel Queue** table. Herold uses the `database` queue driver (SQLite). Processing jobs are dispatched here and picked up by the cron-based worker (`queue:work --stop-when-empty`).

Job types used by Herold:
- `TranscribeAudioJob` — audio file → transcript (Whisper API)
- `PreprocessTranscriptJob` — transcript → structured title + body (Chat API)
- `CreateGitHubIssueJob` — title + body → GitHub issue (GitHub API)
