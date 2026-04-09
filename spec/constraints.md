# Project Constraints

Based on the [Volere Requirements Specification Template](https://www.volere.org/templates/volere-requirements-specification-template/), Section 3 — Mandated Constraints (Robertson & Robertson).
Only sections relevant to Herold are included.

---

## 3a. Solution Constraints (Technology Decisions)

### CON-3a-01: Laravel 13 Monolith

The application is built as a Laravel 13 monolith (PHP 8.5). No microservices,
no separate frontend deployment.

**Rationale:** Single codebase, single deployment, minimal operational complexity
for a personal tool.

### CON-3a-02: Inertia.js + Vue 3 Frontend

The frontend uses Inertia.js 3 with Vue 3.5, TypeScript 6, and Vuetify 4.
No separate SPA, no vue-router, no dedicated API layer for the browser UI.

**Rationale:** See [ADR-001](../adr/001-inertia-frontend-bridge.md).

### CON-3a-03: SQLite Database

SQLite is the sole database. No MySQL, PostgreSQL, or other database server.

**Rationale:** No extra service to manage. Single-user write load is well
within SQLite's capabilities. Portable — the entire database is one file.

### CON-3a-04: Vite 8 Build Toolchain

Frontend assets are built with Vite 8 (Rolldown bundler). Node.js 24 LTS
is required only for the build process, not at runtime.

**Rationale:** Standard Laravel frontend toolchain. Rolldown provides
significantly faster builds.

---

## 3b. Implementation Environment

### CON-3b-01: Shared Hosting (Production)

The production environment is shared hosting with no shell access.
Only FTP upload is available for deployment. Processing is synchronous
(no cron jobs, no queue). See [ADR-002](../adr/002-dev-prod-parity.md).

- No Docker in production
- No Artisan commands via CLI in production
- No long-running processes, no queue worker, no cron jobs
- PHP runs natively on the server
- HTTPS provided by hosting provider

**Rationale:** Existing hosting infrastructure, no budget/need for dedicated server.

### CON-3b-02: Docker Compose (Local Development Only)

Docker Compose is used exclusively for local development. Services:
`app` (PHP 8.5 + Apache), `node` (Vite dev server).
No local PHP, Composer, or Node.js installation required.

The Docker setup intentionally mirrors production (Apache, synchronous
processing) to eliminate dev/prod parity issues. See [ADR-002](../adr/002-dev-prod-parity.md).

**Rationale:** Reproducible development environment with zero dev/prod drift.
Shared hosting does not support Docker (see CON-3b-01).

---

## 3c. Partner and Collaborative Applications

### CON-3c-01: OpenAI API (via Laravel AI SDK)

Audio transcription uses OpenAI Whisper API. Text preprocessing uses
OpenAI Chat Completion API. Both are accessed through the Laravel AI SDK
(`laravel/ai`), which allows switching providers via configuration.

**Rationale:** Whisper provides high-quality German transcription.
Laravel AI SDK enables future provider changes without code modifications.

### CON-3c-02: GitHub Issues API

Tickets are stored as GitHub Issues in a private repository. Access uses
a fine-grained Personal Access Token (PAT) scoped to `Issues: Read & Write`
on a single repository.

**Rationale:** GitHub Issues provides a structured, API-accessible ticket
system that local agents can consume via the `gh` CLI.

### CON-3c-03: Local AI Agents (Claude Code, OpenCode)

Local coding agents consume tickets exclusively via GitHub (`gh` CLI or
GitHub API). Agents do not interact with Herold directly — Herold is a
one-way voice-to-issue dispatcher. Agents manage their own memory locally
via file-based mechanisms (e.g., `CLAUDE.md`).
See [ADR-003](../adr/003-github-issues-as-ticket-store.md).

**Rationale:** Agents already have native GitHub support. No custom API,
no Sanctum tokens, no agent onboarding required.

---

## 3d. Off-the-Shelf Software

### CON-3d-01: Key Dependencies

| Package | Purpose | Constraint |
|---------|---------|------------|
| `laravel/ai` (0.4.x) | AI provider abstraction | Pre-1.0, API may change |
| `laragear/two-factor` (4.0) | TOTP authentication | Must stay compatible with Laravel 13 |
| `inertiajs/inertia-laravel` (3.0) | Server-side Inertia adapter | Must match client version |
| `@inertiajs/vue3` (3.0) | Client-side Inertia adapter | Must match server version |
| `vuetify` (4.0) | UI component library | Breaking change from v3 (MD3) |

---

## 3e. Anticipated Workplace Environment

### CON-3e-01: Mobile Primary, Desktop Secondary

The primary usage context is a smartphone (voice recording on the go).
The user records voice notes while mobile and reviews/edits them later,
potentially on a desktop.

**Rationale:** Voice input is most natural on a mobile device. Review and
editing benefit from a larger screen.

---

## 3f. Schedule Constraints

No deadline. Personal project, developed incrementally.

---

## 3g. Budget Constraints

### CON-3g-01: Existing Hosting

No budget for dedicated servers or managed cloud infrastructure.
Production runs on existing shared hosting.

**Rationale:** See CON-3b-01.

### CON-3g-02: API Costs

OpenAI API usage (Whisper + Chat) incurs per-request costs.
No cost optimization measures planned — volume is low (single user).
