# 2 Architecture Constraints

Constraints fix the non-negotiable design space for the architect. They come from three sources:

- **Externally mandated constraints** from the specification — see [`../spec/P1-constraints.md`](../spec/P1-constraints.md) (Volere § 3) and the cultural / standards requirements in [`../spec/N1-nichtfunktional.md`](../spec/N1-nichtfunktional.md) (§ 16, § 17).
- **Inherited technology decisions** that are now binding for further design. Architecturally significant ones are captured as ADRs in [`A09-architecture-decisions.md`](A09-architecture-decisions.md); the binding facts are summarised here.
- **Project-wide conventions** governing the spec/arch split, documentation structure, and source-tree hygiene — see [`../README.md`](../README.md) and [`../spec/README.md`](../spec/README.md). For the conventions in § 2.3, this chapter is itself the normative source; tooling configuration merely restates them.

The tables below are the architect's working index; detailed rationale stays in the linked sources.

---

## 2.1 Technical Constraints

For inherited technology choices (TECH-08 through TECH-12), versions are recorded only for traceability — the binding constraint is the technology or runtime boundary, not a specific release. `composer.lock` and `package-lock.json` are authoritative for installed dependency versions. Routine upgrades do not change an ADR unless they alter the documented boundary or trade-off.

| ID | Constraint | Description / Source |
|----|------------|----------------------|
| TECH-01 | Shared hosting in production | Apache + PHP on a shared host. No container runtime, no `cron`, no long-running workers; HTTPS at the public edge; out-of-band file-store write access (e.g. FTP) for deployment and recovery. Mandated by [`CON-3b-01`](../spec/P1-constraints.md#con-3b-01-shared-hosting-production); realised by [ADR-002](A09-architecture-decisions.md). |
| TECH-02 | Docker Compose in development | All development services run inside Docker Compose (`docker compose up -d`); no local PHP or Node installation is required. Two services: an `app` container running the same `php:8.5-apache` image used to model production (TECH-10), and a `node` container for the frontend toolchain (TECH-12). Decided in [ADR-002](A09-architecture-decisions.md) to minimise dev/prod drift. |
| TECH-03 | Low-footprint database | No separate database server, schema-management service, or external credential store. Mandated by [`CON-3a-03`](../spec/P1-constraints.md#con-3a-03-low-footprint-database); realised by SQLite (TECH-11). |
| TECH-04 | OpenAI APIs for AI workloads | OpenAI transcription and Chat Completion for content generation. The integration point per neighbour must be replaceable without touching the rest of the application. Mandated by [`CON-3c-01`](../spec/P1-constraints.md#con-3c-01-openai-apis-speech-to-text-and-chat-completion); realised as an application-owned adapter over Laravel HTTP by [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http). |
| TECH-05 | GitHub Issues as sole ticket sink | One private repository, accessed via a fine-grained PAT (`Issues: Read & Write`). Mandated by [`CON-3c-02`](../spec/P1-constraints.md#con-3c-02-github-issues-api); decided in [ADR-003](A09-architecture-decisions.md). |
| TECH-06 | Agent interoperability via GitHub only | Local AI agents (Claude Code, OpenCode, …) read tickets through `gh`/the GitHub API. Herold exposes no agent-facing API and stores no agent memory. Mandated by [`CON-3c-03`](../spec/P1-constraints.md#con-3c-03-local-ai-agents-claude-code-opencode-) and ADR-003. |
| TECH-07 | Mobile and desktop equally supported | Voice recording on smartphone; review and editing on desktop. Neither context is privileged. Mandated by [`CON-3e-01`](../spec/P1-constraints.md#con-3e-01-mobile-and-desktop-equally-supported). |
| TECH-08 | PHP + Laravel | Server-side runtime and framework (PHP 8.5, Laravel 13.4 at the time of this decision). Binding for routing, persistence, validation, and the single-deployable application topology selected in [ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform). AI calls use Laravel's HTTP client behind ADR-007's adapter; no AI SDK is assumed. |
| TECH-09 | Vue + TypeScript + Inertia.js + Vuetify | Browser UI stack (Vue 3.5, Inertia.js 3, Vuetify 4 at the time of this decision; TypeScript source is transpiled by Vite). Inertia bridges Laravel routing/auth and Vue rendering — no separate JSON API for the browser. Decided in [ADR-001](A09-architecture-decisions.md) within the application platform of [ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform). |
| TECH-10 | Apache in development and production | Identical webserver in both environments so that `.htaccess` is exercised continuously. Dev environment uses the official `php:8.5-apache` Docker image (via TECH-02); production runs Apache natively on the host. Decided in [ADR-002](A09-architecture-decisions.md). |
| TECH-11 | SQLite as application database | Concrete realisation of TECH-03 (SQLite 3.51 at the time of this decision). Persisted through a bind-mounted directory in development and as a flat file on the host in production; never overwritten by deployment. Decided in [ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence). |
| TECH-12 | Vite + Node LTS at build time only | Frontend bundling toolchain (Vite 8 with Rolldown, Node 24 LTS at the time of this decision). Node is absent from the production runtime; only assets built off-host are deployed, as decided in [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite). |
| TECH-13 | Home-rolled TOTP | Encrypted `users.totp_secret` plus `users.totp_confirmed_at`; no third-party TOTP package. Surface satisfies [`NFR-15a-01`](../spec/N1-nichtfunktional.md#15a-access-requirements). |

---

## 2.2 Organisational Constraints

| ID | Constraint | Description / Source |
|----|------------|----------------------|
| ORG-01 | Single-developer project | One operator who is also architect and developer. No team, no external review boards, no formal change-control process. |
| ORG-02 | Single-user, single-operator product | Herold is a personal tool: exactly one operator account, no multi-tenancy, no role model, no agent accounts. Mandated by [`CON-3a-04`](../spec/P1-constraints.md#con-3a-04-single-user-system); shapes auth, persistence, and the UI surface. |
| ORG-03 | Existing hosting; no infrastructure budget | No funds for a dedicated server or managed cloud infrastructure. Mandated by [`CON-3g-01`](../spec/P1-constraints.md#con-3g-01-existing-hosting). |
| ORG-04 | OpenAI API costs not actively managed | Single-user volume is low; no per-request cost-optimisation work is in scope. Mandated by [`CON-3g-02`](../spec/P1-constraints.md#con-3g-02-api-costs). |
| ORG-05 | No deadline; incremental development | Personal project, no fixed milestones. See [`P1-constraints.md § 3f`](../spec/P1-constraints.md#3f-schedule-constraints). |
| ORG-06 | Database migrations are operator-driven via SSH | `php artisan migrate --force` is executed manually out-of-band, never during the HTTP request path. Follows from TECH-01 (no deployment hooks, no scheduler on the host); the SSH step is part of the release procedure in [`../../FTP_DEPLOYMENT.md`](../../FTP_DEPLOYMENT.md). |
| ORG-07 | Deployment via FTP from CI | Release tags trigger a GitHub Actions workflow that uploads build artefacts via FTP/TLS. Consequence of TECH-01 (out-of-band file-store write access is the only deployment channel); procedure documented in [`../../FTP_DEPLOYMENT.md`](../../FTP_DEPLOYMENT.md). |

---

## 2.3 Conventions

| ID | Constraint | Description / Source |
|----|------------|----------------------|
| CONV-01 | Language: English everywhere except the method primer | UI text, code, comments, identifiers, documentation, and git commits in English. The only exception is [`../spec/SIEDERSLEBEN.md`](../spec/SIEDERSLEBEN.md), which stays German to keep Siedersleben's original block terminology verbatim. Mandated by [`NFR-16a-01`](../spec/N1-nichtfunktional.md#16a-cultural-requirements). |
| CONV-02 | Conventional Commits | All commits match `type(scope): description`. Mandated by [`NFR-17b-01`](../spec/N1-nichtfunktional.md#17b-standards-compliance). |
| CONV-03 | Spec/arch split is binding | Code-level detail (file paths, class names, library APIs, codecs, SQL, migration steps) does **not** belong in `docs/spec/`. It lives in `docs/arch/` or in the code itself. Stated in [`../README.md`](../README.md) and [`../spec/README.md`](../spec/README.md). |
| CONV-04 | Specification follows the Siedersleben building-block model | Two-letter block codes (`P1`, `F2`, `D1`, …), one file per block. Orchestrated in [`../spec/README.md`](../spec/README.md) and [`../spec/SIEDERSLEBEN.md`](../spec/SIEDERSLEBEN.md). |
| CONV-05 | Architecture documentation follows arc42 9.0 | One file per chapter; numbered file prefix `A<NN>-…`; arc42 numbering inside files (`# 1`, `## 1.1`, …). Orchestrated in [`README.md`](README.md). |
| CONV-06 | Diagrams as versioned PlantUML sources | Sources in `*/diagrams/`, rendered PNGs in the sibling `*/diagrams-png/`. Regenerated via [`../../scripts/generate-diagrams.sh`](../../scripts/generate-diagrams.sh). Schema changes update [`diagrams/relational-datamodel.plantuml`](diagrams/) in the same change set. |
