# 4 Solution Strategy

Short summary of the fundamental decisions. The constraints that bound these decisions are listed in [chapter 2](A02-architecture-constraints.md); the decisions are developed in chapters 5 (building blocks), 6 (runtime), 7 (deployment), 8 (cross-cutting) and 9 (ADRs).

## 4.1 Technology

The stack is read from the bottom up: what the host gives, the languages that run on it, the frameworks and libraries chosen on top, and how they work together at runtime. Versions are in `composer.lock` and `package-lock.json`.

| Layer | Technology | Given or decided |
|-------|------------|------------------|
| Infrastructure | Shared hosting: Apache with `.htaccess`, PHP 8.5 per request, provider HTTPS, FTPS write access, optional limited SSH; no Node, no cron, no worker | Given ([TECH-01](A02-architecture-constraints.md#21-technical-constraints)); PHP extensions in [§ 7.1.1](A07-deployment-view.md#711-production--shared-hosting) |
| | Docker Compose with `php:8.5-apache` and `node:24-alpine` mirrors the host in development | [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing) |
| | GitHub Actions builds and uploads the release artefact; Node 24 exists only there | [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite) |
| Languages | PHP on the server; TypeScript in Vue single-file components, transpiled by Vite without type check | Given (PHP); [ADR-009](A09-architecture-decisions.md#adr-009-vue-3--vuetify--typescript-as-browser-ui-stack), [D-09](A11-risks-and-technical-debts.md#112-technical-debts) |
| Server frameworks and libraries | Laravel (routing, session, validation, encryption, rate limiting, HTTP client) with `inertia-laravel` | [ADR-008](A09-architecture-decisions.md#adr-008-laravel-as-server-side-framework), [ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui) |
| | Eloquent on an SQLite file; uploaded audio on the local filesystem | [ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence) |
| | Application-owned adapters over Laravel's HTTP client for OpenAI and GitHub | [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http) |
| | Home-rolled TOTP on PHP built-ins, no 2FA package | [ADR-010](A09-architecture-decisions.md#adr-010-home-rolled-totp-instead-of-a-library) |
| Browser frameworks and libraries | Vue 3 with `@inertiajs/vue3`; Vuetify 4 with its precompiled styles; Material Design Icons font (`@mdi/font`); `qrcode` for the TOTP enrolment QR code | [ADR-009](A09-architecture-decisions.md#adr-009-vue-3--vuetify--typescript-as-browser-ui-stack) |
| Build and test | Vite with `laravel-vite-plugin` and `@vitejs/plugin-vue`; PHPUnit acceptance tests; Vitest with `@vue/test-utils` and jsdom; Pint as PHP linter | [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite), [§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept) |
| External partners at runtime | OpenAI (transcription, generation) and GitHub Issues (dispatch), called by the server; Google Fonts, loaded by the browser | [ADR-003](A09-architecture-decisions.md#adr-003-github-issues-as-sole-ticket-store), [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http); fonts: no CSP yet, [D-08](A11-risks-and-technical-debts.md#112-technical-debts) |

**How the parts work together.** Apache routes every request to Laravel's front controller. Laravel authenticates the session, validates input, and answers with an Inertia response: on the first visit an HTML shell with the built Vite assets, afterwards the page name and its props as JSON. Vue renders the page with Vuetify components; there is no client-side router and no client store — navigation and form posts go back through Inertia to Laravel ([ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui)). In the pipeline actions, Laravel calls OpenAI and GitHub synchronously inside the same request and stores the results through Eloquent in the SQLite file; no queue, cron, or worker is involved ([ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing)). The host runs only PHP; everything that needs Node is built off-host and uploaded as static files ([ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform), [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite)).

## 4.2 Top-Level Decomposition

One deployable, four logical layers inside the same Apache/PHP process:

**UI shell** (Vue/Inertia) → **web edge** (Laravel routing, single auth gate, validation) → **domain pipeline** (Capture → Process → Send) → **integration & persistence** (one seam per external neighbour, Eloquent on SQLite, audio on the filesystem).

No separate API tier (ADR-001), no worker / queue tier (ADR-002), no agent-facing surface (ADR-003). Developed in [chapter 5](A05-building-block-view.md).

## 4.3 Approach per Quality Goal

| Quality Goal | Approach | Anchored in |
|--------------|----------|-------------|
| [QG-01](A01-introduction-and-goals.md#12-quality-goals) Low operational footprint | One deployable in prod, SQLite flat file, no queue / cron / worker, FTP deploy. | [ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform); [ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence); [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing). |
| [QG-02](A01-introduction-and-goals.md#12-quality-goals) Synchronous responsiveness | Pipeline runs inside the HTTP request; loading indicator throughout; failures surface as data on the entity, not as queued retries. | [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing); [N2.5](../spec/N2-querschnittskonzepte.md#n25-failure-handling). |
| [QG-03](A01-introduction-and-goals.md#12-quality-goals) Type-driven extensibility | Closed `MessageTypeDT` catalogue in code; per-type bindings (label, icon, GitHub label, prompt) in host config; one resolution point. | [N2.2](../spec/N2-querschnittskonzepte.md#n22-type-driven-configuration); [chapter 8](A08-cross-cutting-concepts.md). |
| [QG-04](A01-introduction-and-goals.md#12-quality-goals) AI provider portability | Two application-owned integration seams (transcription, generation); provider code is confined to `AIService`. Model identifiers are still hard-coded ([R-07](A11-risks-and-technical-debts.md#111-risks)). | [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http); [NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements). |
| [QG-05](A01-introduction-and-goals.md#12-quality-goals) Single-user security | Single auth gate (API key + TOTP); server-only secrets typed as `OpaqueSecret`; sanitisation at every persistence and dispatch boundary; redacted logs. | [N2.4](../spec/N2-querschnittskonzepte.md#n24-authentication-and-session), [N2.7](../spec/N2-querschnittskonzepte.md#n27-content-sanitisation), [N2.8](../spec/N2-querschnittskonzepte.md#n28-secret-handling). |

QG-01 and QG-02 are coupled: dropping the asynchronous tier costs a long-running HTTP request. This is the single most consequential strategic decision and is recorded as [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing).

## 4.4 Organisational Approach

Single developer / single operator ([ORG-01, ORG-02](A02-architecture-constraints.md#22-organisational-constraints)). Externally defined templates throughout: Conventional Commits, arc42, Volere / Siedersleben; the spec/arch split is binding ([CONV-03](A02-architecture-constraints.md#23-conventions)). No deadline ([ORG-05](A02-architecture-constraints.md#22-organisational-constraints)). Frontend assets are built off-host ([ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite)); the resulting artefact is uploaded via FTP on a release tag, while the runtime database and `.env` are never overwritten ([ORG-07](A02-architecture-constraints.md#22-organisational-constraints)).
