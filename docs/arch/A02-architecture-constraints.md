# 2 Architecture Constraints

Constraints fix the non-negotiable design space for the architect. They come from two sources:

- **Externally mandated constraints** from the specification — see [`../spec/P1-constraints.md`](../spec/P1-constraints.md) (Volere § 3) and the cultural / standards requirements in [`../spec/N1-nichtfunktional.md`](../spec/N1-nichtfunktional.md) (§ 16, § 17).
- **Project-wide conventions** governing the spec/arch split, documentation structure, and source-tree hygiene — see [`../README.md`](../README.md) and [`../spec/README.md`](../spec/README.md). For the conventions in § 2.3, this chapter is itself the normative source; tooling configuration merely restates them.

Technology choices taken *inside* this space — framework, UI stack, database product, build toolchain, development environment, home-grown components — are architecture decisions, not constraints. They are recorded with their alternatives in [chapter 9](A09-architecture-decisions.md), even where they are binding for further design.

The tables below are the architect's working index; detailed rationale stays in the linked sources.

---

## 2.1 Technical Constraints

| ID | Constraint | Description / Source |
|----|------------|----------------------|
| TECH-01 | Shared hosting with Apache + PHP | The host provides Apache (with per-directory `.htaccess` configuration) and a PHP runtime, invoked per HTTP request, as the only runtime serving the application. No container runtime, no `cron`, no long-running workers, no Node.js or other build toolchain; HTTPS at the public edge; out-of-band file-store write access (e.g. FTP) for deployment and recovery; optional limited shell access (PHP CLI) for one-off maintenance such as schema migrations (ORG-06). The concrete PHP version and extensions are set by the host and recorded in [§ 7.1.1](A07-deployment-view.md#711-production--shared-hosting). Mandated by [`CON-3b-01`](../spec/P1-constraints.md#con-3b-01-shared-hosting-production); shapes [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing), [ADR-004](A09-architecture-decisions.md#adr-004-laravel-monolith-as-a-single-deployable-application-platform), and [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite). |
| TECH-03 | Low-footprint database | No separate database server, schema-management service, or external credential store. Mandated by [`CON-3a-03`](../spec/P1-constraints.md#con-3a-03-low-footprint-database); realised by [ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence). |
| TECH-04 | OpenAI APIs for AI workloads | OpenAI transcription and Chat Completion for content generation. Mandated by [`CON-3c-01`](../spec/P1-constraints.md#con-3c-01-openai-apis-speech-to-text-and-chat-completion). Provider portability is a quality requirement ([NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements), [QG-04](A01-introduction-and-goals.md#12-quality-goals)), realised by [ADR-007](A09-architecture-decisions.md#adr-007-application-owned-ai-adapter-over-laravel-http). |
| TECH-05 | GitHub Issues as ticket target | One private repository, accessed via a fine-grained PAT (`Issues: Read & Write`). Mandated by [`CON-3c-02`](../spec/P1-constraints.md#con-3c-02-github-issues-api). Ticket-target portability is a quality requirement ([NFR-14c-02](../spec/N1-nichtfunktional.md#14c-adaptability-requirements), [QG-06](A01-introduction-and-goals.md#12-quality-goals)). That GitHub is the *sole* ticket store, with no local ticket lifecycle, is decided in [ADR-003](A09-architecture-decisions.md#adr-003-github-issues-as-sole-ticket-store). |
| TECH-06 | Agent interoperability via GitHub only | Local AI agents (Claude Code, OpenCode, …) read tickets through `gh`/the GitHub API. Mandated by [`CON-3c-03`](../spec/P1-constraints.md#con-3c-03-local-ai-agents-claude-code-opencode-); the consequence that Herold exposes no agent-facing API and stores no agent memory is recorded in [ADR-003](A09-architecture-decisions.md#adr-003-github-issues-as-sole-ticket-store). |
| TECH-07 | Mobile and desktop equally supported | Voice recording on smartphone; review and editing on desktop. Neither context is privileged. Mandated by [`CON-3e-01`](../spec/P1-constraints.md#con-3e-01-mobile-and-desktop-equally-supported). |

IDs are stable. TECH-02 and TECH-08 – TECH-13 were technology decisions rather than constraints and have been retired; their content now lives in chapter 9:

| Retired ID | Former entry | Now recorded in |
|------------|--------------|-----------------|
| TECH-02 | Docker Compose in development | [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing) |
| TECH-08 | PHP + Laravel | PHP: TECH-01 (host-given). Laravel: [ADR-008](A09-architecture-decisions.md#adr-008-laravel-as-server-side-framework) |
| TECH-09 | Vue + TypeScript + Inertia.js + Vuetify | Inertia: [ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui). Vue, Vuetify, TypeScript: [ADR-009](A09-architecture-decisions.md#adr-009-vue-3--vuetify--typescript-as-browser-ui-stack) |
| TECH-10 | Apache in development and production | Production: TECH-01 (host-given). Development parity: [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing) |
| TECH-11 | SQLite as application database | [ADR-005](A09-architecture-decisions.md#adr-005-sqlite-as-embedded-persistence) |
| TECH-12 | Vite + Node LTS at build time only | No build toolchain on the host: TECH-01. Off-host Vite build: [ADR-006](A09-architecture-decisions.md#adr-006-off-host-frontend-build-with-vite) |
| TECH-13 | Home-rolled TOTP | [ADR-010](A09-architecture-decisions.md#adr-010-home-rolled-totp-instead-of-a-library) |

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
| CONV-03 | Spec/arch split is binding | Code-level detail (file paths, class names, library APIs, codecs, SQL, migration steps) does **not** belong in `docs/spec/`. It lives in `docs/arch/` or in the code itself. Frameworks, libraries, and other technology choices are architecture decisions and likewise stay out of the spec; product names of neighbouring systems (e.g. OpenAI Whisper, GitHub Issues) and of the host-given runtime (Apache, PHP) are permitted as spec-level constraints. Stated in [`../README.md`](../README.md) and [`../spec/README.md`](../spec/README.md). |
| CONV-04 | Specification follows the Siedersleben building-block model | Two-letter block codes (`P1`, `F2`, `D1`, …), one file per block. Orchestrated in [`../spec/README.md`](../spec/README.md) and [`../spec/SIEDERSLEBEN.md`](../spec/SIEDERSLEBEN.md). |
| CONV-05 | Architecture documentation follows arc42 9.0 | One file per chapter; numbered file prefix `A<NN>-…`; arc42 numbering inside files (`# 1`, `## 1.1`, …). Orchestrated in [`README.md`](README.md). |
| CONV-06 | Diagrams as versioned PlantUML sources | Sources in `*/diagrams/`, rendered PNGs in the sibling `*/diagrams-png/`. Regenerated via [`../../scripts/generate-diagrams.sh`](../../scripts/generate-diagrams.sh). Schema changes update [`diagrams/relational-datamodel.plantuml`](diagrams/) in the same change set. |
