# 9 Architecture Decisions

This document records key architecture decisions made for the project. Each decision documents the context, considered options, and rationale.

For detailed variant comparisons see [`adr/*.md`](../../adr/).

These decisions are taken inside the constraint space of [chapter 2](A02-architecture-constraints.md); technology choices are recorded here, not as constraints. Versions named in a decision are traceability information only — `composer.lock` and `package-lock.json` are authoritative for installed dependency versions. Routine upgrades do not change an ADR unless they alter the documented boundary or trade-off.

---

## ADR-001: Inertia.js as Frontend Bridge (no separate API layer for the browser UI)

**Status:** Accepted

**Context:** Herold is a Laravel monolith with a reactive client-side UI (framework: ADR-009). The question was how server and client should communicate for the operator-facing browser UI. ADR-003 subsequently established that agents interact with GitHub rather than Herold.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- SPA + JSON API** | Vue as standalone SPA, Laravel serves only JSON. Own router, own auth in frontend. | Clear separation, one API for all | Duplicate routing logic, frontend auth overhead, CORS, more boilerplate |
| **B -- Blade + Alpine.js** | Server-side rendering with Blade, Alpine for interactivity. | Simplest setup, no JS build | Audio UI too complex for Alpine, no SPA feeling |
| **C -- Inertia.js** | Laravel routing + auth + validation, Vue only for rendering. SPA feeling without a separate API layer. | One routing system, no frontend auth overhead, first-party adapters for Vue, React, and Svelte | No general-purpose JSON API, additional concept |

**Decision:** Option C -- Inertia.js.

**Rationale:** A monolith does not need a separate API layer for the browser UI. Inertia eliminates duplicate routing, frontend auth, and manual error handling. The complex audio UI (MediaRecorder, waveform) requires a reactive client framework -- Blade would not suffice. The browser UI runs via Inertia (`web.php` + session). Agents interact directly with GitHub, not with Herold (see ADR-003). Detailed variant comparison: [`adr/001-inertia-frontend-bridge.md`](../../adr/001-inertia-frontend-bridge.md).

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
| **C -- Laravel monolith** | One Laravel application with the Vue/Inertia UI compiled into the same release artefact. | One codebase and deployment, full reactive UI, minimal operations | Coupled releases, binding Laravel platform (PHP itself is host-given, TECH-01) |

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

---

## ADR-008: Laravel as Server-Side Framework

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30

**Context:** The host fixes PHP as the only server-side runtime (TECH-01). ADR-004 decides the topology (one deployable) but not the framework. Herold needs routing, session authentication, CSRF protection, validation, relational persistence with migrations on SQLite (ADR-005), an HTTP client for two external APIs (ADR-007), encrypted model attributes, and a server-side adapter for the Inertia bridge (ADR-001).

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- Plain PHP / micro-framework** | Slim or hand-written front controller plus selected components. | Smallest footprint, full control | Session, CSRF, validation, migrations, encryption, and testing support must be assembled and maintained by hand |
| **B -- Symfony** | Full-stack component framework. | Mature, explicit configuration, long-term support releases | Encrypted entity attributes need a third-party Doctrine extension; rate limiter, HTTP client and test doubles are separate components to wire; Inertia adapter is community-maintained |
| **C -- Laravel** | Full-stack framework with Eloquent, migrations, HTTP client, and first-party Inertia and Vite integration. | Every needed concern built in; first-party Inertia adapter and Vite plugin; runs on plain Apache + PHP via `.htaccess` | Large `vendor/` tree in every release upload; annual major releases; operations use `artisan`, which needs a PHP CLI on the host ([R-08](A11-risks-and-technical-debts.md#111-risks)) |

**Decision:** Option C -- Laravel.

**Rationale:** Laravel covers every server-side concern Herold needs without assembling additional platform components, and it runs on the host as delivered — Apache rewrites to `public/index.php`, no daemon, only standard PHP extensions (listed in [§ 7.1.1](A07-deployment-view.md#711-production--shared-hosting)). The first-party Inertia adapter and Vite plugin make the UI stack of ADR-001/ADR-009 a supported configuration rather than an integration project. The framework ships inside the release artefact; the host only provides PHP. Detailed variant comparison: [`adr/008-laravel-server-side-framework.md`](../../adr/008-laravel-server-side-framework.md).

---

## ADR-009: Vue 3 + Vuetify + TypeScript as Browser UI Stack

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30

**Context:** ADR-001 rules out server-rendered Blade for the audio UI and selects Inertia as the bridge; Inertia has first-party adapters for Vue, React, and Svelte, so the bridge does not presuppose a framework. Two choices remain: the client framework with its component library, and the source language. Criteria: the product requirements — responsive from 320 px with touch targets ≥ 48 dp ([NFR-10a-01](../spec/N1-nichtfunktional.md#10a-appearance-requirements)), smartphone and desktop equally (TECH-07); delivery effort for one developer ([ORG-01](A02-architecture-constraints.md#22-organisational-constraints)); and familiarity — the developer already works with Vue 3 and Vuetify, an explicitly weighted criterion that lowers delivery risk but is no product benefit.

**Options — framework and component library:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- React + MUI** | React with a Material component library. | Largest ecosystem, first-party Inertia adapter; meets the product requirements as well as D | Unfamiliar (JSX, hook rules, MUI) |
| **B -- Svelte** | Compiler-based framework, custom or small component library. | Small bundles, little runtime | Smaller component ecosystem; most UI components to be built; unfamiliar |
| **C -- Vue 3 + Tailwind / headless components** | Vue with utility CSS and unstyled primitives. | Full visual control | Accessibility, touch sizing, and responsive behaviour must be designed per component |
| **D -- Vue 3 + Vuetify** | Vue 3 Composition API with Vuetify's Material Design 3 components. | First-party Inertia adapter; responsive grid and touch-sized components out of the box; familiar | Vuetify's visual language and major-version upgrades; Material look must be adapted rather than designed freely |

**Options — source language:**

| Option | Pros | Cons |
|--------|------|------|
| **JavaScript** | No annotations to maintain | Prop and payload shapes at the Inertia boundary stay implicit |
| **TypeScript** | Props, page payloads, and type projections explicit in the source; editor support | Vite strips annotations without checking them; enforcement needs a separate `vue-tsc` check; types kept in step with PHP by hand |

**Decision:** Option D -- Vue 3 with Vuetify; components and scripts are written in TypeScript.

**Rationale:** On the product requirements, A and D are equivalent; familiarity decides between them, which for a single developer without deadline ([ORG-05](A02-architecture-constraints.md#22-organisational-constraints)) is the cheapest way to lower delivery risk. B and C fail the delivery-effort criterion independently of familiarity. TypeScript makes the data shapes at the Inertia boundary explicit; enforced types need the type-check gate that is still missing, tracked as technical debt [D-09](A11-risks-and-technical-debts.md#112-technical-debts). Detailed variant comparison: [`adr/009-vue-vuetify-typescript-ui-stack.md`](../../adr/009-vue-vuetify-typescript-ui-stack.md).

---

## ADR-010: Home-Rolled TOTP instead of a Library

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30

**Context:** The second authentication factor ([NFR-15a-01](../spec/N1-nichtfunktional.md#15a-access-requirements)) is a time-based one-time password. It runs inside Herold's own two-step sign-in flow (API key, then TOTP, with inline enrolment) and its file-based recovery ([UC-03](../spec/F2-anwendungsfaelle.md#uc-03--recover-access)). The question is whether TOTP generation and verification come from a package or from application code. The deciding criterion is the size of the supply chain: every package, including its transitive dependencies, is an entry point for a compromised release and must be watched for advisories.

**Options:**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A -- Laravel Fortify** | First-party authentication backend with two-factor support. | Maintained, complete 2FA flow | Brings its own routes, flows, and recovery codes that compete with Herold's API-key login and file-based recovery; mostly unused code in the supply chain |
| **B -- TOTP library** | e.g. `pragmarx/google2fa` for secret generation and code verification. | Widely reviewed implementation; drift window configurable per call; constant-time comparison built in | Adds the library and its transitive dependency to the supply chain for about a hundred lines of algorithm |
| **C -- Home-rolled RFC 6238/4226** | Secret generation, Base32 codec, HOTP/TOTP calculation, and verification in a dedicated `TotpService` on PHP built-ins. | No additional package; small, isolated, unit-testable against the RFC test vectors | Security-relevant code owned by the project; edge cases (32-bit counter, lenient Base32 decoding) must be known and tested by the project |

**Decision:** Option C -- home-rolled TOTP per RFC 6238/4226.

**Rationale:** The algorithm is short, standardised, and stable; PHP's `random_bytes`, `hash_hmac`, and `hash_equals` provide every primitive it needs, and the published RFC 6238 test vectors cover the implementation risk. A library would grow the supply chain without removing any flow logic from Herold; Fortify would replace rather than support Herold's sign-in and recovery flows. Detailed variant comparison: [`adr/010-home-rolled-totp.md`](../../adr/010-home-rolled-totp.md).
