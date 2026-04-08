# Herold - Agent Guidelines

## Project

Voice-based task dispatcher for local AI agents. Laravel 13 monolith with Inertia.js + Vue 3 + Vuetify 4. See `spec/herold-spec.prompt.md` for full specification, `DESIGN.md` for UI design guidelines, and `docs/ARCHITECTURE_DECISIONS.md` for ADRs.

## Language

- **User communication:** German (Deutsch)
- **Code, comments, variable names:** English
- **Documentation (README, ADRs, docs/):** English
- **UI text (labels, buttons, messages):** English
- **Git commits:** conventional commit messages in English
- **Exception:** `spec/herold-spec.prompt.md` remains in German (project specification, written collaboratively in German)

## Development

- Everything runs in Docker (`docker compose up -d`)
- No local PHP/Node required
- Docker setup mirrors production: Apache + cron-based queue (see ADR-002)
- Laravel commands: `docker compose exec app php artisan <command>`
- Queue: cron runs `schedule:run` every minute (same as prod). For faster dev feedback: `docker compose exec app php artisan queue:work`
- SQLite database, persisted via Docker volume

## Architecture

- **Browser UI:** Inertia.js routes in `web.php` (Session auth)
- **Agent API:** JSON routes in `api.php` (Sanctum token auth)
- Message types are config-driven (`config/herold.php`), not code-driven
- Production: shared hosting (FTP + cron, no shell access)
