# 9 Architecture Decisions

This document records key architecture decisions made for the project. Each decision documents the context, considered options, and rationale.

For detailed variant comparisons see [`adr/*.md`](../../adr/).

---

## ADR-001: Inertia.js as Frontend Bridge (no separate API layer for the browser UI)

**Status:** Accepted

**Context:** Herold is a Laravel monolith with a Vue frontend. The question was how Laravel and Vue should communicate for the operator-facing browser UI. ADR-003 subsequently established that agents interact with GitHub rather than Herold.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- SPA + JSON API** | Vue as standalone SPA, Laravel serves only JSON. Own router, own auth in frontend. | Clear separation, one API for all | Duplicate routing logic, frontend auth overhead, CORS, more boilerplate |
| **B -- Blade + Alpine.js** | Server-side rendering with Blade, Alpine for interactivity. | Simplest setup, no JS build | Audio UI too complex for Alpine, no SPA feeling |
| **C -- Inertia.js** | Laravel routing + auth + validation, Vue only for rendering. SPA feeling without a separate API layer. | One routing system, no frontend auth overhead, Vue experience reusable | No general-purpose JSON API, additional concept |

**Decision:** Option C -- Inertia.js.

**Rationale:** A monolith does not need a separate API layer for the browser UI. Inertia eliminates duplicate routing, frontend auth, and manual error handling. The complex audio UI (MediaRecorder, waveform) requires Vue -- Blade would not suffice. The browser UI runs via Inertia (`web.php` + session). Agents interact directly with GitHub, not with Herold (see ADR-003). Detailed variant comparison: [`adr/001-inertia-frontend-bridge.md`](../../adr/001-inertia-frontend-bridge.md).

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

**Decision:** Option C -- Docker Compose with Apache + synchronous processing.

**Rationale:** Docker Compose provides a reproducible local environment without requiring host PHP or Node installations. Apache parity ensures `.htaccess` is tested in development. Synchronous processing eliminates disproportionate queue infrastructure (cron service, HTTP-cron endpoint, job classes, polling) for a single-user project. The ~10-30s wait is acceptable with a loading indicator. Two development services (`app` + `node`), no cron configuration on shared hosting. Detailed variant comparison: [`adr/002-dev-prod-parity.md`](../../adr/002-dev-prod-parity.md).

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

**Rationale:** Herold does one thing well: capture voice input and dispatch it as a GitHub Issue. Agents use GitHub natively (`gh` CLI). GitHub provides audit trail and agent communication (comments) for free. Agent memory was speculative -- it can be added later if a real need emerges. Detailed variant comparison: [`adr/003-github-issues-as-ticket-store.md`](../../adr/003-github-issues-as-ticket-store.md).

---

## ADR-004: Laravel Monolith as a Single-Deployable Application Platform

**Status:** Accepted

**Context:** Herold needs a reactive browser UI but must run as a low-footprint application on shared Apache/PHP hosting. The question is whether server, browser UI, and domain/integration logic should be independently deployed or form one application platform.

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- Separate backend and SPA** | JSON API and independently deployed browser application. | Independent releases, explicit network boundary | Two deployment units, duplicate routing/auth concerns, CORS/API overhead |
| **B -- Multiple backend services** | Capture, processing, persistence, and dispatch run separately. | Runtime isolation, independent scaling | Unsupported by the host, distributed failure modes, disproportionate operations |
| **C -- Laravel monolith** | One Laravel application with the Vue/Inertia UI compiled into the same release artefact. | One codebase and deployment, full reactive UI, minimal operations | Coupled releases, binding PHP/Laravel platform |

**Decision:** Option C -- one Laravel application and one deployable.

**Rationale:** The host naturally supports one PHP application, and the single-operator workload provides no reason to distribute it. Logical boundaries remain explicit in code; ADR-001 governs the Inertia browser bridge and ADR-006 the frontend build boundary. Dependency versions are inventory in the lockfiles, not permanent architecture constraints. Detailed variant comparison: [`adr/004-single-deployable-application-platform.md`](../../adr/004-single-deployable-application-platform.md).

---

## ADR-005: SQLite as Embedded Persistence

**Status:** Accepted

**Context:** Herold needs relational persistence but the production environment and quality goals exclude a separately operated database service.

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- Server RDBMS** | PostgreSQL or MySQL as a separate service. | Better high-concurrency operation, mature remote administration | Extra service, credentials, backups, and host dependency |
| **B -- Application-managed files** | Persist records as JSON or custom flat files. | No database engine | Application must recreate transactions, locking, indexing, and migrations |
| **C -- SQLite** | Embedded relational database in one persistent file. | No server, Laravel support, constraints and transactions, portable file | Serialized writes, filesystem and backup responsibility |

**Decision:** Option C -- SQLite as the sole application database.

**Rationale:** SQLite satisfies the low-footprint constraint while retaining relational guarantees and Laravel migrations. The single-operator load does not justify a server database. The file is a protected persistent deployment surface; the database-level singleton trigger enforces the one-operator invariant. Detailed variant comparison: [`adr/005-sqlite-as-embedded-persistence.md`](../../adr/005-sqlite-as-embedded-persistence.md).

---

## ADR-006: Off-Host Frontend Build with Vite

**Status:** Accepted

**Context:** The Vue UI requires a build step, but the shared production host has no Node.js toolchain. The release must arrive with runnable static assets.

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- Build in production** | Install dependencies and run the frontend build on the host. | Build occurs in target environment | Toolchain unavailable, mutates production, partial-build risk |
| **B -- Commit generated assets** | Keep `public/build/` in version control. | No release build required | Noisy diffs and source/build drift |
| **C -- Build off-host** | Vite builds from lockfiles in development/CI; release uploads static output. | Repeatable CI gate, no Node runtime in production | Release automation must assemble the complete artefact |

**Decision:** Option C -- Vite builds off-host; production receives `public/build/` only.

**Rationale:** CI can reproduce and validate the build from lockfiles, while the production runtime remains Apache/PHP only. Node and Vite are build-time tools rather than deployed components. Detailed variant comparison: [`adr/006-off-host-frontend-build.md`](../../adr/006-off-host-frontend-build.md).

---

## ADR-007: Application-Owned AI Adapter over Laravel HTTP

**Status:** Accepted

**Context:** OpenAI transcription and chat generation must remain replaceable integration seams. The question is whether provider calls should use a generic AI SDK, a provider SDK, or direct HTTP behind Herold's own boundary.

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- `laravel/ai`** | First-party Laravel AI abstraction. | Potential common provider API | Additional dependency; must prove support for both seams |
| **B -- Provider SDK** | OpenAI-specific client package. | Provider-maintained request types | Provider coupling remains; SDK types can leak into callers |
| **C -- Application adapter + Laravel HTTP** | `AIService` owns endpoints, payloads, validation, and timeouts using `Http`. | No extra dependency, exact control, stable application-facing seam | Herold owns provider-contract maintenance |

**Decision:** Option C -- direct Laravel HTTP calls confined to `AIService`.

**Rationale:** Two small integration surfaces do not justify another abstraction layer. Provider portability comes from the application-owned adapter contract: controllers and pipeline services contain no OpenAI request knowledge. A possible migration to `laravel/ai` is tracked in [issue #41](https://github.com/carstenlucke/herold/issues/41) and must preserve that boundary. Detailed variant comparison: [`adr/007-application-owned-ai-adapter.md`](../../adr/007-application-owned-ai-adapter.md).
