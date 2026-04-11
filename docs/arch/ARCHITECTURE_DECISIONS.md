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

**Rationale:** A monolith does not need a separate API layer for the browser UI. Inertia eliminates duplicate routing, frontend auth, and manual error handling. The complex audio UI (MediaRecorder, waveform) requires Vue -- Blade would not suffice. The browser UI runs via Inertia (`web.php` + session). Agents interact directly with GitHub, not with Herold (see ADR-003). Detailed variant comparison: [`adr/001-inertia-frontend-bridge.md`](../adr/001-inertia-frontend-bridge.md).

---

## ADR-002: Dev/Prod Parity -- Apache + Synchronous Processing

**Status:** Accepted

**Context:** Production runs on shared hosting (Apache, FTP, limited SSH). The Docker dev setup must minimize dev/prod drift. Additionally, the processing pipeline (Whisper + LLM + GitHub push, ~10-30s) needs a processing strategy -- async queue or synchronous. These decisions are coupled: async would require cron infrastructure in both environments.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- nginx + PHP-FPM + worker** | Standard Docker pattern. Persistent queue worker. | Common pattern, instant job processing | Two dev/prod differences (webserver + queue). `.htaccess` untested. 4 containers. |
| **B -- Apache + cron queue** | `php:8.5-apache` image. Cron-based queue. HTTP-cron endpoint for prod. | Full dev/prod parity | Cron service, HTTP-cron endpoint, job classes, polling, 8-state enum. 3 containers. |
| **C -- Apache + synchronous** | `php:8.5-apache` image. All processing in the HTTP request. No queue. | Apache parity, no queue infrastructure, 2 containers, minimal code | Blocking request (~10-30s), no auto-retry |
| **D -- Native (no Docker)** | Local PHP + Composer + Node. | Closest to prod | Requires local installation, not reproducible |

**Decision:** Option C -- Apache + synchronous processing.

**Rationale:** Apache parity ensures `.htaccess` is tested in dev. Synchronous processing eliminates disproportionate queue infrastructure (cron service, HTTP-cron endpoint, job classes, polling) for a single-user demo project. The ~10-30s wait is acceptable with a loading indicator. 2 Docker services (`app` + `node`), no cron config on shared hosting. Detailed variant comparison: [`adr/002-dev-prod-parity.md`](../adr/002-dev-prod-parity.md).

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
