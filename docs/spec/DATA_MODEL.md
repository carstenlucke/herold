# Herold Data Model

Visual diagram: [`datamodel.plantuml`](datamodel.plantuml) (generate PNG with `./scripts/generate-diagrams.sh`).

---

## Domain Entities

### voice_notes

The central entity of Herold. Each row represents one voice note from recording through ticket creation.

| Column | Purpose |
|--------|---------|
| `id` | ULID primary key (time-sortable, no auto-increment) |
| `type` | Message type (`general`, `youtube`, `diary`, `obsidian`, `todo`). Not an enum — validated against `config/herold.php` so new types require only a config entry, no code change. |
| `status` | Processing state, see [NoteStatus](#notestatus). |
| `audio_path` | Relative path in `storage/app/private/audio/`. Set on upload, nullable until then. |
| `transcript` | Raw transcription from OpenAI Whisper. |
| `processed_title` | LLM-generated title for the GitHub issue. Editable by user before sending. |
| `processed_body` | LLM-generated issue body in **Markdown** format. Editable by user before sending. |
| `metadata` | Type-specific JSON data. Schema depends on `type`, defined via `extra_fields` in `config/herold.php`. Examples: youtube `{"youtube_url": "..."}`, diary `{"entry_date": "2026-04-11"}`, obsidian `{"vault": "Work"}`, todo `{"deadline": "2026-04-20"}`. |
| `github_issue_number` | GitHub issue number after successful creation. |
| `github_issue_url` | Full URL to the created GitHub issue. |
| `error_message` | Error details when a processing step fails. |

---

## Enums

### NoteStatus

Tracks progress through the voice-note processing pipeline. Processing is synchronous (see [ADR-002](../../adr/002-dev-prod-parity.md)), so only four states are needed.

| Value | Meaning |
|-------|---------|
| `recorded` | Audio saved, awaiting user action |
| `processed` | Transcription + LLM preprocessing completed, ready for user review |
| `sent` | GitHub issue created successfully (final state) |
| `error` | A processing step failed — user can retry |

During synchronous processing, the UI shows a loading indicator. There are no intermediate states — the request either succeeds (→ `processed` or `sent`) or fails (→ `error`).

---

## Laravel Framework Tables

These tables are not Herold domain entities but are required by the Laravel framework. Their schema is defined by Laravel and should not be modified.

### users

Standard Laravel `users` table. Herold is a **single-user system** — this table holds exactly one row. Required by Laravel's auth system.

Notable columns:
- `remember_token` — Laravel's "Remember Me" cookie token. **Unused** in Herold (we use API-Key + TOTP authentication, not username/password with "Remember Me").
