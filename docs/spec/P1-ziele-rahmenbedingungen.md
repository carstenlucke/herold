# P1 — Goals and Constraints

Foundation block of the Herold specification according to Siedersleben. Answers: Why is the system built, who is it for, and which constraints frame the solution space?

---

## P1.1 Mission

Herold is a single-user voice-to-issue dispatcher. The user records a voice note in the browser; the system transcribes it, derives structured content, and pushes the result as a GitHub Issue into a private repository. Local AI coding agents (e.g. Claude Code, OpenCode) consume the issues through GitHub's native interfaces and act on them.

Herold replaces the cognitive overhead of typing well-formed tickets — whether away from the keyboard (walking, driving) or at the desk, where speaking a note is faster than composing structured Markdown by hand.

---

## P1.2 Business Goals

| ID | Goal |
|----|------|
| G-01 | Capture task intent in the moment it occurs — voice instead of typing. |
| G-02 | Convert raw speech into a structured, agent-readable ticket without manual reformatting. |
| G-03 | Keep operational overhead near zero (runs on the existing shared hosting, no additional services to operate). |
| G-04 | Stay open for additional message types (general, YouTube, diary, …) via configuration only. |
| G-05 | Use existing agent infrastructure (GitHub Issues, which agents already read natively) instead of a custom agent interface. |

---

## P1.3 Stakeholders and Users

| Role | Description | Interaction with Herold |
|------|-------------|-------------------------|
| **Operator** | Sole human user. Owns and operates the system. | Records, reviews, edits, sends notes via the browser UI. |
| **Local AI agents** | Claude Code, OpenCode, etc. | No direct interaction. Read tickets from GitHub. |
| **Hosting provider** | Shared hosting operator. | Provides the runtime environment per [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production): HTTPS at the public web edge, an out-of-band write channel into the host file store, and optional limited shell access. |
| **External services** | OpenAI, GitHub. | Used while a note is processed or dispatched; the operator waits for the result. |

Multi-user support is explicitly out of scope (see [CON-3a-04](P1-constraints.md#con-3a-04-single-user-system)).

---

## P1.4 Scope

### In scope

- Browser UI for recording, reviewing, editing, and dispatching voice notes.
- Processing on operator request (transcription → content generation) and dispatch to GitHub, each completed while the operator waits.
- Configuration-driven message types with type-specific prompts and metadata.
- Single-user authentication (API key + TOTP) with recovery via out-of-band access to the host.
- One-way push to GitHub Issues — no read-back, no synchronization.

### Out of scope

| ID | Non-goal | Rationale |
|----|----------|-----------|
| NG-01 | Multi-user accounts, roles, permissions | Personal tool for one operator. |
| NG-02 | Agent control API, agent memory, agent onboarding | Agents use GitHub natively (see [CON-3c-03](P1-constraints.md#con-3c-03-local-ai-agents-claude-code-opencode-)). |
| NG-03 | Local ticket lifecycle (status sync, comments, closing) | GitHub is the sole ticket store (see [CON-3c-02](P1-constraints.md#con-3c-02-github-issues-api)). |
| NG-04 | Asynchronous processing (deferred or scheduled processing in the background) | Voice notes are short enough to be processed while the operator waits; shared hosting offers no scheduled or long-running processing (see [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production)). |
| NG-05 | Offline use, installation as a standalone app | Connectivity assumed; complexity not justified. |
| NG-06 | Native mobile apps | A responsive web UI covering smartphone and desktop is sufficient. |
| NG-07 | Reports, exports, print output | No reporting use case. |
| NG-08 | Migration of legacy data | Greenfield project. |
| NG-09 | Long-form audio (meetings, lectures, podcasts) | Herold dispatches voice notes that capture task intent in the moment. Long-form transcription is a different problem domain and could not be processed while the operator waits (see [NFR-12a-01](N1-nichtfunktional.md)). |

---

## P1.5 Constraints

Detailed constraints with rationale are kept as an annex in [`P1-constraints.md`](P1-constraints.md), structured along Volere Section 3 (Mandated Constraints). The annex is the authoritative source; this section is an index.

| ID | Constraint |
|----|------------|
| CON-3a-03 | Low-footprint database — no separate server, minimal operational overhead |
| CON-3a-04 | Single-user system |
| CON-3b-01 | Shared hosting (production) — no container runtime, no scheduler, deployment via out-of-band file-store access |
| CON-3c-01 | OpenAI APIs (Whisper for transcription, Chat Completion for text generation), isolated behind a single integration boundary |
| CON-3c-02 | GitHub Issues API (fine-grained PAT) |
| CON-3c-03 | Local AI agents consume tickets via GitHub only |
| CON-3e-01 | Mobile and desktop equally supported |
| CON-3g-01 | Existing hosting (no budget for dedicated infrastructure) |
| CON-3g-02 | OpenAI API costs unmanaged (low single-user volume) |

Technology choices (language, framework, database product, build toolchain, local development tooling, dependency versions) are *design decisions* and live in [`docs/arch/`](../arch/); architecturally significant choices and their rationale are recorded in [chapter 9 — Architecture Decisions](../arch/A09-architecture-decisions.md).

---

## P1.6 Success Criteria

| ID | Criterion |
|----|-----------|
| SC-01 | A voice note recorded in the browser results in a well-formed GitHub Issue with type label, title, and Markdown body — without manual reformatting. |
| SC-02 | End-to-end latency (record → issue created) is dominated by external APIs (typically a few seconds for short notes, ~10–15 s for notes up to ~2 minutes), with no additional waiting time introduced by Herold. |
| SC-03 | Adding a new message type requires only a configuration entry plus a prompt — no change to the software or to the stored data structure. |
| SC-05 | Loss of API key or TOTP secret is recoverable without provider support, using only the operator's out-of-band access to the host and the recovery flow. |

The earlier criterion SC-04 (same runtime behaviour in development and production) has been retired: it is a design goal recorded in the architecture decisions, not a success criterion of the product. Its identifier is not reused.

---

## P1.7 Assumptions

| ID | Assumption |
|----|------------|
| AS-01 | The operator has a GitHub account and can issue a fine-grained PAT for one private repository. |
| AS-02 | The operator has an OpenAI API key with access to Whisper and a Chat model. |
| AS-03 | The hosting provider serves HTTPS — required by the browser audio capture used in UC-05. |
| AS-04 | Voice notes are short enough to fit within OpenAI's audio size limit (≤ 25 MB per recording). |
| AS-05 | The hosting environment lets a single processing or dispatch step run to completion within the latency budget of [NFR-12a-01](N1-nichtfunktional.md) (up to ~30 s). |

---

## P1.8 Risks

| ID | Risk | Mitigation |
|----|------|------------|
| R-01 | OpenAI API outage or rate limit blocks the entire pipeline. | Status does not advance; the error is recorded on the note; the operator retries (see [NFR-12d-01](N1-nichtfunktional.md)). No silent failures. |
| R-02 | GitHub API outage prevents dispatch. | Note remains in status `processed`; the operator retries the dispatch. |
| R-03 | The hosting environment aborts a processing or dispatch step midway. | The note and its recording are preserved and the operator can retry. If the abort happens after the issue was created but before the note is marked as sent, a retry creates a duplicate issue; the system does not assume the issue was created ([UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note)), and this residual risk is accepted. |
| R-04 | Prompt-injection content in transcripts leaks into agent context. | Voice-note content is treated as untrusted: active markup is removed before content is stored or dispatched, and in the issue operator-derived content is visually delimited from application-generated structure ([AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation), [NFR-15b-04](N1-nichtfunktional.md)). |
| R-05 | Loss of API key or TOTP locks the operator out. | Out-of-band recovery flow with a one-time recovery token ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access); see also [NFR-14a-02](N1-nichtfunktional.md)). |
