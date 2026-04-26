# Herold - Agent Guidelines

## Project

Voice-based task dispatcher for local AI agents. Laravel 13 monolith with Inertia.js + Vue 3 + Vuetify 4. See `prompts/herold-spec.prompt.md` for full specification, `DESIGN.md` for UI design guidelines, and `docs/arch/ARCHITECTURE_DECISIONS.md` for ADRs.

## Stack

| Component | Version | Notes |
|-----------|---------|-------|
| Laravel | 13.4.x | First-party AI SDK (`laravel/ai`) |
| PHP | 8.5 | Docker image: `php:8.5-apache` |
| Composer | 2.9.x | |
| Vue.js | 3.5.x | Composition API |
| TypeScript | 6.x | |
| Vuetify | 4.x | MD3, CSS Cascade Layers |
| Inertia.js | 3.x | Laravel + Vue adapters |
| Vite | 8.x | Rolldown bundler (Rust) |
| Node.js | 24 LTS | Build only, not runtime |
| SQLite | 3.51.x | |
| laragear/two-factor | 4.x | TOTP auth |

## Architecture

- **Backend:** Laravel 13 monolith handling web routes, session auth, and processing orchestration.
- **Frontend:** Inertia.js + Vue 3 + Vuetify as the browser UI layer.
- **Persistence:** SQLite for application data (notes, states, metadata, outbound issue references) plus local file storage for uploaded audio.
- **AI integration:** OpenAI APIs for speech-to-text and text generation; behavior is driven by configured message types and prompts.
- **Ticket target:** GitHub Issues as external sink (one-way push only). No local issue lifecycle management and no agent control API.
- **Operations:** Migrations are executed manually via SSH (`php artisan migrate --force`), never during the HTTP request path.

### Processing pipeline (synchronous request flow)

1. **Record:** User uploads audio with metadata; the note is stored with status `recorded`.
2. **Process:** User starts processing; backend transcribes audio, derives structured note content, then sets status `processed`.
3. **Review & send:** User can edit the generated content; backend sanitizes markdown, creates a GitHub issue, stores issue reference data, then sets status `sent`.

## Development Environment

- Everything runs in Docker (`docker compose up -d`)
- No local PHP/Node required
- Laravel commands: `docker compose exec app php artisan <command>`
- SQLite database, persisted via Docker volume

## Production Environment (Shared Hosting)

- PHP 8.5 runs natively (no Docker)
- Apache with DocumentRoot pointing to `public/`
- Deployment via FTP/TLS upload (GitHub Actions workflow on release tag)
- Limited SSH access: PHP 8.5 available, `crontab` blocked
- HTTPS provided by hosting provider (required for MediaRecorder API)
- SQLite database file on server — never overwritten by deployment
- `.env` with production values — manually placed, never overwritten

## Documentation

- **Diagrams:** PlantUML sources live in `docs/spec/diagrams/`, generated PNGs in `docs/spec/diagrams-png/`. When changing the database schema (migrations, models, enums), update `docs/spec/diagrams/datamodel.plantuml`. Regenerate all PNGs with `./scripts/generate-diagrams.sh`.

## Language

- **User communication:** German (Deutsch)
- **Code, comments, variable names:** English
- **Documentation (README, ADRs, docs/):** English
- **UI text (labels, buttons, messages):** English
- **Git commits:** conventional commit messages in English
- **Exception:** `prompts/herold-spec.prompt.md` remains in German (project specification, written collaboratively in German)
