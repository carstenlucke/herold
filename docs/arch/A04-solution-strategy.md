# 4 Solution Strategy

Short summary of the fundamental decisions. The stack itself is catalogued in [§ 2.1](A02-architecture-constraints.md#21-technical-constraints); the decisions are developed in chapters 5 (building blocks), 6 (runtime), 7 (deployment), 8 (cross-cutting) and 9 (ADRs).

## 4.1 Technology

- **Laravel monolith** on **Apache + PHP 8.5**, one deployable in production ([ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform)); Apache is mirrored in development by Docker Compose ([ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing)).
- **Inertia.js + Vue + Vuetify** as the browser UI — no separate JSON API for the browser ([ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui)).
- **SQLite** as the embedded flat-file RDBMS ([ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence)); uploaded audio on the local filesystem.
- **HTTPS** outbound to **OpenAI** through an application-owned Laravel HTTP adapter ([ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http)) and to **GitHub Issues** — synchronous, blocking, no queue or worker ([ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing)).
- **GitHub Issues as the sole ticket store**; local AI agents are indirect via GitHub, never via Herold ([ADR-003](A09-architecture-decisions.md#adr-003-github-issues-as-sole-ticket-store)).

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
| [QG-04](A01-introduction-and-goals.md#12-quality-goals) AI provider portability | Two application-owned integration seams (transcription, generation); provider code is confined to `AIService`. Model identifiers are still hard-coded ([R-07](A11-risks-and-technical-debts.md#111-risks)). | TECH-04; [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http); [NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements). |
| [QG-05](A01-introduction-and-goals.md#12-quality-goals) Single-user security | Single auth gate (API key + TOTP); server-only secrets typed as `OpaqueSecret`; sanitisation at every persistence and dispatch boundary; redacted logs. | [N2.4](../spec/N2-querschnittskonzepte.md#n24-authentication-and-session), [N2.7](../spec/N2-querschnittskonzepte.md#n27-content-sanitisation), [N2.8](../spec/N2-querschnittskonzepte.md#n28-secret-handling). |

QG-01 and QG-02 are coupled: dropping the asynchronous tier costs a long-running HTTP request. This is the single most consequential strategic decision and is recorded as [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing).

## 4.4 Organisational Approach

Single developer / single operator ([ORG-01, ORG-02](A02-architecture-constraints.md#22-organisational-constraints)). Externally defined templates throughout: Conventional Commits, arc42, Volere / Siedersleben; the spec/arch split is binding ([CONV-03](A02-architecture-constraints.md#23-conventions)). No deadline ([ORG-05](A02-architecture-constraints.md#22-organisational-constraints)). Frontend assets are built off-host ([ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite)); the resulting artefact is uploaded via FTP on a release tag, while the runtime database and `.env` are never overwritten ([ORG-07](A02-architecture-constraints.md#22-organisational-constraints)).
