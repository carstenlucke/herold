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
- Docker setup mirrors production: Apache (see ADR-002), 2 services: `app` + `node`
- Laravel commands: `docker compose exec app php artisan <command>`
- Processing is synchronous (no queue, no cron — see ADR-002)
- SQLite database, persisted via Docker volume

## Architecture

- **Browser UI:** Inertia.js routes in `web.php` (Session auth)
- **Ticket store:** GitHub Issues (one-way push, see ADR-003). No local ticket management, no agent API.
- Message types are config-driven (`config/herold.php`), not code-driven
- Production: shared hosting (FTP, no shell access)

## Documentation

- **Data model diagram:** `docs/datamodel.plantuml` — when changing database schema (migrations, models, enums), check if the PlantUML diagram needs updating. Regenerate PNG with `./scripts/generate-diagrams.sh`.
