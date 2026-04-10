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
(Browser)     (OpenAI Whisper)     (Title, Structure)     (typed)           (via gh + Cron)
```

Herold is the interface between human and AI agent swarm: you speak, Herold translates into structured tasks, your agents execute them.

## Features

- **Voice Recording** in the browser (MediaRecorder API)
- **Automatic Transcription** via OpenAI Whisper
- **LLM Preprocessing** -- generates titles, structures content, cleans up speech artifacts
- **Typed Tickets** -- different message types with distinct processing pipelines
- **GitHub Issues** as ticket system (private repo, labels for type + status)
- **Agent Memory API** -- shared memory for local agents (SQLite-based)
- **Dual Auth** -- browser (API key + TOTP) and agents (Sanctum bearer tokens)
- **Queue-based Processing** -- UI is not blocked during API calls

## Message Types

| Type | Input | Description |
|------|-------|-------------|
| **General** | Voice message | General tasks -- transcript is structured into a ticket |
| **YouTube** | Voice + URL | Instructions via voice, video URL in ticket -- agent processes later |
| **Diary** | Voice message | Diary entry -- formatted with date, mood, reflection |

New types can be added via a config entry in `config/herold.php` -- no code changes required.

## Ticket-Lifecycle

```
status:open  -->  status:in_progress  -->  status:done  -->  status:verified
(App)             (Agent)                  (Agent)           (Mensch)
```

## Tech Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Backend | Laravel | 13.4 |
| Language | PHP | 8.5 |
| Frontend | Vue 3 + Inertia.js 3 | 3.5 / 3.0 |
| UI | Vuetify | 4.0 |
| Build | Vite (Rolldown) | 8.0 |
| AI | Laravel AI SDK (`laravel/ai`) | 0.4 |
| Transcription | OpenAI Whisper API | |
| Tickets | GitHub Issues API | |
| Auth (Browser) | API Key + TOTP | laragear/two-factor 4.0 |
| Auth (Agents) | Laravel Sanctum | |
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
# Edit .env: APP_KEY, OPENAI_API_KEY, GITHUB_TOKEN, HEROLD_API_KEY

# Generate APP_KEY
docker compose run --rm app php artisan key:generate

# Start all services
docker compose up -d
```

Migrations and seeding run automatically on container start via
`docker-entrypoint.sh`. No manual `php artisan migrate` required.

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

# Full reset (removes database volume)
docker compose down -v
```

## Agent-API

Local agents authenticate with Sanctum bearer tokens (created via the Settings page).

```bash
# Memories lesen
curl -H "Authorization: Bearer herold_..." \
     http://localhost:8080/api/memories?scope=global

# Memory speichern
curl -X POST -H "Authorization: Bearer herold_..." \
     -H "Content-Type: application/json" \
     -d '{"scope":"global","category":"learning","content":"...","source":"claude-code"}' \
     http://localhost:8080/api/memories

# Tickets lesen
curl -H "Authorization: Bearer herold_..." \
     http://localhost:8080/api/tickets?status=open

# Ticket-Status aendern
curl -X PATCH -H "Authorization: Bearer herold_..." \
     -H "Content-Type: application/json" \
     -d '{"status":"in_progress"}' \
     http://localhost:8080/api/tickets/42/status
```

### Token Scopes

| Scope | Description |
|-------|-------------|
| `memory:read` | Read and search memories |
| `memory:write` | Create and delete memories |
| `tickets:read` | List tickets |
| `tickets:status` | Update ticket status |

## Project Structure

```
herold/
  spec/                     # Specification, NFRs, constraints
  adr/                      # Architecture Decision Records
  docs/                     # Architecture decisions index
  app/
    Http/Controllers/       # Web + API controllers
    Models/                 # VoiceNote, Memory, User
    Services/               # AIService, GitHubService, MemoryService, PreprocessingService
    Jobs/                   # TranscribeAudio, PreprocessTranscript, CreateGitHubIssue
    Enums/                  # NoteStatus, MessageType, MemoryScope, MemoryCategory
  config/herold.php         # Message type registry + app config
  resources/js/
    Pages/                  # Inertia/Vue pages
    Components/             # Reusable Vue components
    Composables/            # Vue composition functions
  routes/
    web.php                 # Browser routes (session auth)
    api.php                 # Agent API routes (Sanctum token auth)
```

## License

Private project.
