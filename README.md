# Herold

Voice-based task dispatcher for local AI agents.

Record voice messages, transcribe them, structure them via LLM, and file them as typed tickets in GitHub Issues -- for automatic processing by local coding agents (Claude Code, OpenCode).

## Teaching Context

This project serves as a **demonstration project** for two courses in the [B.Sc. Wirtschaftsinformatik](https://www.thm.de/site/studium/unsere-studienangebote/wirtschaftsinformatik-bachelor-bsc-mnd-friedberg.html) program at Technische Hochschule Mittelhessen (THM):

- **WK_1208 Softwaretechnik** -- Software Engineering
- **WK_1106 Wirtschaftsinformatik-Projekt I (Softwaretechnik)** -- Applied Software Engineering Project

The project illustrates real-world practices in:

- **Requirements Engineering** -- Non-functional requirements and constraints documented using the [Volere template](https://www.volere.org/templates/volere-requirements-specification-template/) (see [`spec/non-functional-requirements.md`](spec/non-functional-requirements.md), [`spec/constraints.md`](spec/constraints.md))
- **Software Requirements Specification** -- Structured specification with message type registry, ticket lifecycle, API design (see [`spec/herold.md`](spec/herold.md))
- **Software Architecture Documentation** -- Architecture decisions recorded as ADRs (see [`docs/ARCHITECTURE_DECISIONS.md`](docs/ARCHITECTURE_DECISIONS.md), [`adr/`](adr/)), architecture documentation conforming to [arc42](https://arc42.org)

## Konzept

```
Sprache  -->  Transkription  -->  LLM-Aufbereitung  -->  GitHub Issue  -->  Lokaler Agent
(Browser)     (OpenAI Whisper)    (Titel, Struktur)      (typisiert)       (via gh + Cron)
```

Herold ist die Schnittstelle zwischen Mensch und KI-Agent-Schwarm: Du sprichst, Herold uebersetzt in strukturierte Auftraege, deine Agenten arbeiten sie ab.

## Features

- **Sprachaufnahme** im Browser (MediaRecorder API)
- **Automatische Transkription** via OpenAI Whisper
- **LLM-Vorverarbeitung** -- generiert Titel, strukturiert Inhalt, bereinigt Sprach-Artefakte
- **Typisierte Tickets** -- verschiedene Nachrichtentypen mit unterschiedlicher Verarbeitung
- **GitHub Issues** als Ticket-System (privates Repo, Labels fuer Typ + Status)
- **Agent Memory API** -- geteiltes Gedaechtnis fuer lokale Agenten (SQLite-basiert)
- **Dual-Auth** -- Browser (API-Key + TOTP) und Agenten (Sanctum Bearer Tokens)
- **Queue-basierte Verarbeitung** -- UI blockiert nicht bei API-Calls

## Nachrichtentypen

| Typ | Input | Beschreibung |
|-----|-------|-------------|
| **General** | Sprachnachricht | Allgemeine Aufgaben -- Transkript wird in strukturiertes Ticket aufbereitet |
| **YouTube** | Sprache + URL | Anweisungen per Sprache, Video-URL im Ticket -- Agent verarbeitet spaeter |
| **Diary** | Sprachnachricht | Tagebucheintrag -- formatiert mit Datum, Stimmung, Reflexion |

Neue Typen koennen durch einen Config-Eintrag in `config/herold.php` hinzugefuegt werden -- kein Code noetig.

## Ticket-Lifecycle

```
status:open  -->  status:in_progress  -->  status:done  -->  status:verified
(App)             (Agent)                  (Agent)           (Mensch)
```

## Tech-Stack

| Komponente | Technologie | Version |
|-----------|-------------|---------|
| Backend | Laravel | 13.4 |
| Sprache | PHP | 8.5 |
| Frontend | Vue 3 + Inertia.js 3 | 3.5 / 3.0 |
| UI | Vuetify | 4.0 |
| Build | Vite (Rolldown) | 8.0 |
| AI | Laravel AI SDK (`laravel/ai`) | 0.4 |
| Transkription | OpenAI Whisper API | |
| Tickets | GitHub Issues API | |
| Auth (Browser) | API-Key + TOTP | laragear/two-factor 4.0 |
| Auth (Agenten) | Laravel Sanctum | |
| Datenbank | SQLite | 3.51 |
| Infrastruktur | Docker Compose | 5.1 |

## Voraussetzungen

- Docker Engine >= 29.x
- Docker Compose >= 5.x
- OpenAI API Key
- GitHub Fine-grained PAT (Issues: Read & Write, auf ein privates Repo beschraenkt)

Kein lokales PHP, Composer oder Node.js erforderlich -- alles laeuft in Docker.

## Setup

```bash
# Repository klonen
git clone git@github.com:<user>/herold.git
cd herold

# Environment konfigurieren
cp .env.example .env
# .env editieren: OPENAI_API_KEY, GITHUB_PAT, HEROLD_API_KEY, etc.

# Container starten
docker compose up -d

# Laravel initialisieren
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# Frontend-Dependencies
docker compose exec node npm install
```

## Entwicklung

```bash
# All services (App/Apache + Cron + Vite Dev Server)
docker compose up -d

# App:  http://localhost:8080
# Vite: http://localhost:5173 (HMR)

# Laravel commands
docker compose exec app php artisan <command>

# Queue jobs are processed by cron (every minute)
# For faster processing during dev:
docker compose exec app php artisan queue:work

# Cron logs
docker compose logs -f cron

# Stop
docker compose down
```

## Agent-API

Lokale Agenten authentifizieren sich mit Sanctum Bearer Tokens (erstellbar in der Settings-Seite).

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

### Token-Scopes

| Scope | Beschreibung |
|-------|-------------|
| `memory:read` | Memories lesen und suchen |
| `memory:write` | Memories erstellen und loeschen |
| `tickets:read` | Tickets auflisten |
| `tickets:status` | Ticket-Status aendern |

## Projektstruktur

```
herold/
  spec/                     # Specification, NFRs, constraints
  adr/                      # Architecture Decision Records
  docs/                     # Architecture decisions index
  app/
    Http/Controllers/       # Web + API Controller
    Models/                 # VoiceNote, Memory, User
    Services/               # AIService, GitHubService, MemoryService, PreprocessingService
    Jobs/                   # TranscribeAudio, PreprocessTranscript, CreateGitHubIssue
    Enums/                  # NoteStatus, MessageType, MemoryScope, MemoryCategory
  config/herold.php         # Nachrichtentyp-Registry + App-Config
  resources/js/
    Pages/                  # Inertia/Vue Seiten
    Components/             # Wiederverwendbare Vue-Komponenten
    Composables/            # Vue Composition Functions
  routes/
    web.php                 # Browser-Routen (Session-Auth)
    api.php                 # Agent-API-Routen (Sanctum Token-Auth)
```

## Lizenz

Privates Projekt.
