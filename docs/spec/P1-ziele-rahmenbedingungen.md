# P1 — Goals and Constraints

Foundation block of the Herold specification according to Siedersleben. Answers: Why is the system built, who is it for, and which constraints frame the solution space?

---

## P1.1 Mission

Herold is a single-user voice-to-issue dispatcher. The user records a voice note on a mobile device; the system transcribes it, derives structured content, and pushes the result as a GitHub Issue into a private repository. Local AI coding agents (e.g. Claude Code, OpenCode) consume the issues through GitHub's native interfaces and act on them.

Herold replaces the cognitive overhead of typing well-formed tickets while walking, driving, or otherwise away from the keyboard.

---

## P1.2 Business Goals

| ID | Goal |
|----|------|
| G-01 | Capture task intent in the moment it occurs — voice instead of typing. |
| G-02 | Convert raw speech into a structured, agent-readable ticket without manual reformatting. |
| G-03 | Keep operational overhead near zero (single deployable, no queue, no cron, shared hosting). |
| G-04 | Stay open for additional message types (general, YouTube, diary, …) via configuration only. |
| G-05 | Use existing agent infrastructure (GitHub Issues + `gh` CLI) instead of a custom agent API. |

---

## P1.3 Stakeholders and Users

| Role | Description | Interaction with Herold |
|------|-------------|-------------------------|
| **Operator** | Sole human user. Owns and operates the system. | Records, reviews, edits, sends notes via the browser UI. |
| **Local AI agents** | Claude Code, OpenCode, etc. | No direct interaction. Read tickets from GitHub. |
| **Hosting provider** | Shared hosting operator. | Provides PHP 8.5, Apache, HTTPS, FTP, limited SSH. |
| **External APIs** | OpenAI, GitHub. | Called synchronously during the processing pipeline. |

Multi-user support is explicitly out of scope (see CON-3a-04 in [constraints.md](constraints.md)).

---

## P1.4 Scope

### In scope

- Browser UI for recording, reviewing, editing, and dispatching voice notes.
- Synchronous processing pipeline: transcription → content generation → GitHub push.
- Configuration-driven message types with type-specific prompts and metadata.
- Single-user authentication (API key + TOTP) with file-based recovery.
- One-way push to GitHub Issues — no read-back, no synchronization.

### Out of scope

| ID | Non-goal | Rationale |
|----|----------|-----------|
| NG-01 | Multi-user accounts, roles, permissions | Personal tool for one operator. |
| NG-02 | Agent control API, agent memory, agent onboarding | Agents use GitHub natively (see ADR-003). |
| NG-03 | Local ticket lifecycle (status sync, comments, closing) | GitHub is the sole ticket store (see ADR-003). |
| NG-04 | Asynchronous processing (queues, workers, cron) | Synchronous pipeline fits shared hosting (see ADR-002). |
| NG-05 | PWA, service worker, offline mode | Connectivity assumed; complexity not justified. |
| NG-06 | Native mobile apps | Mobile-first responsive web is sufficient. |
| NG-07 | Reports, exports, print output | No reporting use case. |
| NG-08 | Migration of legacy data | Greenfield project. |

---

## P1.5 Constraints

Detailed constraints with rationale are kept as an annex in [`P1-constraints.md`](P1-constraints.md), structured along Volere Section 3 (Mandated Constraints). The annex is the authoritative source; this section is an index.

| ID | Constraint |
|----|------------|
| CON-3a-01 | Laravel 13 monolith |
| CON-3a-02 | Inertia.js + Vue 3 + Vuetify 4 |
| CON-3a-03 | SQLite as sole database |
| CON-3a-04 | Single-user system (DB-enforced) |
| CON-3a-05 | Vite 8 build toolchain |
| CON-3b-01 | Shared hosting (production) — no Docker, no cron, FTP deployment |
| CON-3b-02 | Docker Compose (local development only) |
| CON-3c-01 | OpenAI API via Laravel AI SDK |
| CON-3c-02 | GitHub Issues API (fine-grained PAT) |
| CON-3c-03 | Local AI agents consume tickets via GitHub only |
| CON-3d-01 | Key dependencies (Laravel AI SDK, two-factor, Inertia, Vuetify) |
| CON-3e-01 | Mobile-primary, desktop-secondary |
| CON-3g-01 | Existing hosting (no budget for dedicated infrastructure) |
| CON-3g-02 | OpenAI API costs unmanaged (low single-user volume) |

---

## P1.6 Success Criteria

| ID | Criterion |
|----|-----------|
| SC-01 | A voice note recorded on a smartphone results in a well-formed GitHub Issue with type label, title, and Markdown body — without manual reformatting. |
| SC-02 | End-to-end latency (record → issue created) is dominated by external APIs (~10–30 s), with no additional polling or cron delays. |
| SC-03 | Adding a new message type requires only a configuration entry plus a prompt — no PHP, no Vue, no migration. |
| SC-04 | The same Apache + PHP 8.5 + SQLite stack runs in development (Docker) and production (shared hosting) without environment-specific code paths. |
| SC-05 | Loss of API key or TOTP secret is recoverable without provider support, using only FTP and the recovery flow. |

---

## P1.7 Assumptions

| ID | Assumption |
|----|------------|
| AS-01 | The operator has a GitHub account and can issue a fine-grained PAT for one private repository. |
| AS-02 | The operator has an OpenAI API key with access to Whisper and a Chat model. |
| AS-03 | The hosting provider serves HTTPS — required by the browser audio capture used in UC-05. |
| AS-04 | Voice notes are short enough to fit within OpenAI's per-request audio limit (≤ 25 MB). |
| AS-05 | Synchronous requests of 10–30 s are not killed by the hosting provider's PHP/Apache timeouts. |

---

## P1.8 Risks

| ID | Risk | Mitigation |
|----|------|------------|
| R-01 | OpenAI API outage or rate limit blocks the entire pipeline. | Status `error` is set; user retries. No silent failures. |
| R-02 | GitHub API outage prevents dispatch. | Note remains in status `processed`; user retries `send`. |
| R-03 | Hosting timeout cuts a synchronous request mid-processing. | Idempotent retry from current status; partial state is recoverable. |
| R-04 | Prompt-injection content in transcripts leaks into agent context. | `IssueContentSanitizer` separates untrusted input and strips active markup. |
| R-05 | Loss of API key or TOTP locks the operator out. | Out-of-band recovery flow via `RecoveryToken` (UC-03; see also NFR-14a-02). |
