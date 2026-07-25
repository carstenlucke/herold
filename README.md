# Herold

Voice-based task dispatcher for local AI agents.

Record voice messages, transcribe them, structure them via LLM, and file them as typed tickets in GitHub Issues -- for automatic processing by local coding agents (Claude Code, OpenCode).

## Teaching Context

This project serves as a **demonstration project** for two courses in the [B.Sc. Wirtschaftsinformatik](https://www.thm.de/site/studium/unsere-studienangebote/wirtschaftsinformatik-bachelor-bsc-mnd-friedberg.html) program at Technische Hochschule Mittelhessen (THM):

- **WK_1208 Softwaretechnik** -- Software Engineering
- **WK_1106 Wirtschaftsinformatik-Projekt I (Softwaretechnik)** -- Applied Software Engineering Project


## Concept

```
Voice    -->  Transcription   -->  LLM Processing   -->  GitHub Issue  -->  Local Agent
(Browser)     (OpenAI API)          (Title, Structure)     (typed)           (via gh)
```

Herold is the interface between human and AI agent swarm: you speak, Herold translates into structured tasks, your agents execute them.

## Features

- **Voice Recording** in the browser (MediaRecorder API)
- **Automatic Transcription** via OpenAI Whisper
- **LLM Preprocessing** -- generates titles, structures content, cleans up speech artifacts
- **Typed Tickets** -- different message types with distinct processing pipelines
- **GitHub Issues** as the sole ticket system (private repo, labels for type)
- **Browser Authentication** with API key + TOTP
- **Synchronous Processing** inside the operator's HTTP request; no queue, cron, or worker
- **Native Agent Workflow** through GitHub and the `gh` CLI; no Herold agent API

## Message Types

| Type | Input | Description |
|------|-------|-------------|
| **General** | Voice message | General tasks -- transcript is structured into a ticket |
| **YouTube** | Voice + URL | Instructions via voice, video URL in ticket -- agent processes later |
| **Diary** | Voice message | Diary entry -- formatted with date, mood, reflection |

New types can be added via a config entry in `config/herold.php` -- no code changes required.

## Downstream Ticket Lifecycle

Herold's responsibility ends after creating the GitHub issue. Agents and the operator may apply their own GitHub label convention, for example:

```
status:open  -->  status:in_progress  -->  status:done  -->  status:verified
```

## Tech Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Backend | Laravel | 13.4 |
| Language | PHP | 8.5 |
| Frontend | Vue 3 + Inertia.js 3 | 3.5 / 3.0 |
| UI | Vuetify | 4.0 |
| Build | Vite (Rolldown) | 8.0 |
| AI client | Application-owned adapter over Laravel HTTP | Laravel 13.4 |
| Transcription | OpenAI API | |
| Tickets | GitHub Issues API | |
| Auth (Browser) | API Key + home-rolled TOTP | RFC 6238/4226 |
| Auth (Agents) | None — agents use GitHub via `gh` | |
| Database | SQLite | 3.51 |
| Infrastructure | Docker Compose | 5.1 |

## Prerequisites

- Docker Engine >= 29.x
- Docker Compose >= 5.x
- OpenAI API Key
- GitHub Fine-grained PAT (Issues: Read & Write, scoped to a single private repo)

No local PHP, Composer, or Node.js required -- everything runs in Docker.

## Setup

```bash
# Clone repository
git clone git@github.com:<user>/herold.git
cd herold

# Configure environment
cp .env.example .env
# Edit .env: APP_KEY, OPENAI_API_KEY, HEROLD_GITHUB_TOKEN, HEROLD_API_KEY, HEROLD_ADMIN_EMAIL

# Generate APP_KEY
docker compose run --rm app php artisan key:generate

# Start all services
docker compose up -d
```

Migrations run automatically on container start via `docker-entrypoint.sh`.
No manual `php artisan migrate` required. The initial admin user is created
by a migration on first run.

## Development

```bash
# Start services (App/Apache + Vite Dev Server)
docker compose up -d

# App:  http://localhost:8080
# Vite: http://localhost:5173 (HMR)

# Laravel commands
docker compose exec app php artisan <command>

# Stop
docker compose down

# Remove database
rm database/data/database.sqlite
```

## Agent Workflow

Herold exposes no agent-facing API. Local agents consume and update the dispatched tickets directly through GitHub:

```bash
gh issue list
gh issue view <number>
gh issue comment <number> --body "..."
```

Authentication and agent-specific memory remain outside Herold.

## Project Structure

```
herold/
  adr/                      # Architecture Decision Records (detailed variants)
  docs/
    arch/                   # Architecture decisions index
    spec/                   # Specification, NFRs, constraints, data model
  app/
    Http/Controllers/       # Operator-facing web controllers
    Models/                 # VoiceNote, User
    Services/               # Pipeline logic and external adapters
    Logging/                # Secret-redacting log processors
    Enums/                  # NoteStatus
  config/herold.php         # Message type registry + app config
  resources/js/
    Pages/                  # Inertia/Vue pages
    Components/             # Reusable Vue components
    Composables/            # Vue composition functions
  routes/
    web.php                 # Browser routes (session auth)
    console.php             # Stock Artisan console route
```

## License

Private project.
