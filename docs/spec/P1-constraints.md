# P1 — Project Constraints (Annex)

Detailed mandated constraints referenced from [`P1-ziele-rahmenbedingungen.md`](P1-ziele-rahmenbedingungen.md) § P1.5. Constraints define the non-negotiable solution space — they are distinct from non-functional requirements (see [`N1-nichtfunktional.md`](N1-nichtfunktional.md)).

Based on the [Volere Requirements Specification Template](https://www.volere.org/templates/volere-requirements-specification-template/), Section 3 — Mandated Constraints (Robertson & Robertson). Only sections relevant to Herold are included.

This annex captures only constraints that are externally mandated or otherwise non-negotiable at spec level. Technology choices (language, framework, database product, build toolchain, local development tooling, dependency versions) are *design decisions* taken inside the constraint space and live in [`docs/arch/`](../arch/); items previously misclassified as constraints and now awaiting ADR work are tracked in [`docs/arch/arch-backlog.md`](../arch/arch-backlog.md).

---

## 3a. Solution Constraints

### CON-3a-03: Low-Footprint Database

The system uses a database technology that imposes no significant deployment effort and only minimal operational overhead — no separate database server to install, run, monitor, or back up out-of-band; no schema-management service; no additional credentials store. The concrete product is an architecture concern.

**Rationale:** Herold runs on shared hosting (CON-3b-01) for a single operator (CON-3a-04). A dedicated database server would dominate operational effort and dwarf the actual workload.

### CON-3a-04: Single-User System

Herold supports exactly one operator account. Multi-user, multi-tenant, and agent-account models are out of scope. The single-instance property is enforced at the persistence layer; the concrete enforcement mechanism is an architecture concern.

**Rationale:** Herold is a personal tool for a single operator. Multi-user would add authentication complexity (roles, permissions, account management) with no benefit. Agents interact with GitHub, not with Herold (see [ADR-003](../arch/003-github-issues-as-ticket-store.md)).

---

## 3b. Implementation Environment

### CON-3b-01: Shared Hosting (Production)

The production environment is shared hosting. The application must run within the following limitations:

- No container runtime
- No long-running processes, no queue worker, no scheduled jobs (`cron` not available)
- HTTPS provided by the hosting provider
- Out-of-band file-store write access (e.g. via FTP) for deployment and for the recovery channel of [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)
- Optional limited shell access for one-off maintenance only

The application must not depend on any of the missing facilities. See [ADR-002](../arch/002-dev-prod-parity.md).

**Rationale:** Existing hosting infrastructure; no budget for a dedicated server (CON-3g-01).

---

## 3c. Partner and Collaborative Applications

### CON-3c-01: OpenAI APIs (Speech-to-Text and Chat Completion)

Audio transcription uses the OpenAI Whisper API; text preprocessing uses the OpenAI Chat Completion API. Both are mandated as the system's external AI providers. The consumption layer is configuration-switchable so that the provider can be replaced without code changes; concrete client and protocol details are an architecture concern.

**Rationale:** Whisper provides high-quality German transcription; Chat Completion is the matching text-generation surface. Provider-switchability isolates the spec from later provider changes.

### CON-3c-02: GitHub Issues API

Tickets are stored as GitHub Issues in a private repository. Access uses a fine-grained Personal Access Token (PAT) scoped to `Issues: Read & Write` on a single repository.

**Rationale:** GitHub Issues provides a structured, API-accessible ticket system that local agents can consume via the `gh` CLI.

### CON-3c-03: Local AI Agents (Claude Code, OpenCode, …)

Local coding agents consume tickets exclusively via GitHub (`gh` CLI or GitHub API). Agents do not interact with Herold directly — Herold is a one-way voice-to-issue dispatcher. Agents manage their own memory locally via file-based mechanisms (e.g., `CLAUDE.md`). See [ADR-003](../arch/003-github-issues-as-ticket-store.md).

**Rationale:** Agents already have native GitHub support. No custom API, no agent-side credentials issued by Herold, no agent onboarding required.

---

## 3e. Anticipated Workplace Environment

### CON-3e-01: Mobile and Desktop Equally Supported

The application is operated from both a smartphone (voice recording on the go) and a desktop (review, editing, configuration). Neither context is privileged over the other.

**Rationale:** Voice input is most natural on a mobile device, while review and editing benefit from a larger screen. Both flows are core to the operator's workflow.

---

## 3f. Schedule Constraints

No deadline. Personal project, developed incrementally.

---

## 3g. Budget Constraints

### CON-3g-01: Existing Hosting

No budget for dedicated servers or managed cloud infrastructure. Production runs on existing shared hosting.

**Rationale:** See CON-3b-01.

### CON-3g-02: API Costs

OpenAI API usage (Whisper + Chat Completion) incurs per-request costs. No cost optimisation measures planned — volume is low (single user).
