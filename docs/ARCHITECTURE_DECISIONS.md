# Architecture Decisions

This document records key architecture decisions made for the project. Each decision documents the context, considered options, and rationale.

For detailed variant comparisons see [`adr/*.md`](../adr/).

---

## ADR-001: Inertia.js as Frontend Bridge (no separate API layer for the browser UI)

**Status:** Accepted

**Context:** Herold is a Laravel monolith with a Vue frontend and two consumer types: a human (browser) and AI agents (CLI/curl). The question was how Laravel and Vue should communicate.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- SPA + JSON API** | Vue as standalone SPA, Laravel serves only JSON. Own router, own auth in frontend. | Clear separation, one API for all | Duplicate routing logic, frontend auth overhead, CORS, more boilerplate |
| **B -- Blade + Alpine.js** | Server-side rendering with Blade, Alpine for interactivity. | Simplest setup, no JS build | Audio UI too complex for Alpine, no SPA feeling |
| **C -- Inertia.js** | Laravel routing + auth + validation, Vue only for rendering. SPA feeling without a separate API layer. | One routing system, no frontend auth overhead, Vue experience reusable | Agents need separate API routes, additional concept |

**Decision:** Option C -- Inertia.js.

**Rationale:** A monolith does not need a separate API layer for the browser UI. Inertia eliminates duplicate routing, frontend auth, and manual error handling. The complex audio UI (MediaRecorder, waveform) requires Vue -- Blade would not suffice. Agents get a separate API (`api.php` + Sanctum), the browser UI runs via Inertia (`web.php` + session). Clean separation, minimal overhead. Detailed variant comparison: [`adr/001-inertia-frontend-bridge.md`](../adr/001-inertia-frontend-bridge.md).

---

## ADR-002: Dev/Prod Parity -- Apache + Cron in Docker

**Status:** Accepted

**Context:** Production runs on shared hosting (Apache, cron, FTP, no shell). The Docker dev setup must minimize dev/prod drift to prevent "works on my machine" bugs that only surface after FTP deployment.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- nginx + PHP-FPM + worker** | Standard Docker pattern. Persistent queue worker. | Common pattern, instant job processing | Two dev/prod differences (webserver + queue). `.htaccess` untested in dev. |
| **B -- Apache + cron** | `php:8.5-apache` image. Cron-based queue like prod. | Zero dev/prod drift, fewer containers, `.htaccess` tested | Up to 1-min queue delay, larger image |
| **C -- Native (no Docker)** | Local PHP + Composer + Node. | Closest to prod | Requires local installation, not reproducible |

**Decision:** Option B -- Apache + cron in Docker.

**Rationale:** Every component that matters (Apache, cron queue, `.htaccess`) is identical in dev and prod. This eliminates the most common deployment bugs for shared hosting. Simpler setup (3 services instead of 4, no nginx.conf). The 1-minute queue delay is acceptable for a single-user app. Detailed variant comparison: [`adr/002-dev-prod-parity.md`](../adr/002-dev-prod-parity.md).

**Note:** Partially superseded by ADR-004. The cron-based queue has been removed in favor of synchronous processing. The Apache parity argument remains valid.

---

## ADR-003: GitHub Issues as Sole Ticket Store

**Status:** Accepted

**Context:** The original design stored tickets locally (SQLite) and maintained a parallel agent memory system, with GitHub Issues as one delivery channel. This gave Herold three responsibilities: voice processing, ticket management, and agent memory.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- All local** | Tickets + memory in SQLite. Agents use Herold API. | Single data source, no external deps | Full ticket CRUD + memory system to build, Herold becomes a platform |
| **B -- Hybrid** | GitHub for tickets, SQLite for memory | Native agent support for tickets | Two systems for agents, memory is speculative |
| **C -- GitHub only, defer memory** | Herold is a voice-to-GitHub dispatcher. No local tickets, no memory. | Clear single responsibility, minimal code | External dependency, no offline tickets, no agent memory |

**Decision:** Option C -- GitHub Issues as sole ticket store, agent memory deferred.

**Rationale:** Herold does one thing well: capture voice input and dispatch it as a GitHub Issue. Agents use GitHub natively (`gh` CLI). GitHub provides audit trail and agent communication (comments) for free. Agent memory was speculative -- it can be added later if a real need emerges. Detailed variant comparison: [`adr/003-github-issues-as-ticket-store.md`](../adr/003-github-issues-as-ticket-store.md).

---

## ADR-004: Synchronous Processing (no queue, no cron)

**Status:** Accepted (partially supersedes ADR-002)

**Context:** The original design used Laravel's queue system with cron-based workers for async processing (transcription, LLM, GitHub push). This required a cron Docker service, an HTTP-cron endpoint with Basic Auth for production, job classes, and frontend status polling.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- Async queue + cron** | Jobs dispatched to queue, cron worker processes them | Non-blocking UI, retry logic | Cron service, HTTP-cron endpoint, job classes, polling, 8-state enum |
| **B -- Synchronous** | All steps run in a single HTTP request | No queue, no cron, drastically simpler | Blocking request (~10-30s), no auto-retry |
| **C -- Sync + deferred GitHub push** | Transcription + LLM sync, GitHub push separate | Fast feedback, review step | Two-step UX (already exists in design) |

**Decision:** Option B -- Synchronous processing.

**Rationale:** Herold is a single-user demo project. The ~10-30s wait is acceptable with a loading indicator. Removing the queue eliminates: cron Docker service, `CronController`, HTTP-cron endpoint, three job classes, frontend polling, and intermediate `NoteStatus` states. Docker Compose reduced from 3 to 2 services. Shared hosting deployment simplified (no cron config needed). Detailed variant comparison: [`adr/004-synchronous-processing.md`](../adr/004-synchronous-processing.md).
