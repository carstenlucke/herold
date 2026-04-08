# Herold - Agent Guidelines

## Project

Voice-based task dispatcher for local AI agents. Laravel 13 monolith with Inertia.js + Vue 3 + Vuetify 4. See `spec/herold.md` for full specification and `docs/ARCHITECTURE_DECISIONS.md` for ADRs.

## Language

- **User communication:** German (Deutsch)
- **Code, comments, variable names:** English
- **Documentation (README, ADRs, docs/):** English
- **UI text (labels, buttons, messages):** English
- **Git commits:** conventional commit messages in English
- **Exception:** `spec/herold.md` remains in German (project specification, written collaboratively in German)

## Development

- Everything runs in Docker (`docker compose up -d`)
- No local PHP/Node required
- Laravel commands: `docker compose exec app php artisan <command>`
- Queue worker runs as separate Docker service
- SQLite database, persisted via Docker volume

## Architecture

- **Browser UI:** Inertia.js routes in `web.php` (Session auth)
- **Agent API:** JSON routes in `api.php` (Sanctum token auth)
- Message types are config-driven (`config/herold.php`), not code-driven
