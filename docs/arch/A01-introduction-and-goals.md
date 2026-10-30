# 1 Introduction and Goals

Herold is a single-user, voice-to-issue dispatcher: the operator records a voice note in the browser; the system transcribes it, derives a structured ticket, and pushes the result as a GitHub Issue into a private repository, where local AI coding agents pick it up.

This chapter summarises the requirements and quality goals that frame the architecture. The authoritative requirements specification lives in [`../spec/`](../spec/) and is referenced from here rather than duplicated.

---

## 1.1 Requirements Overview

The functional core, in one paragraph: the operator authenticates with API key plus TOTP, picks a message type, records audio in the browser, and submits the note. Herold transcribes the audio via OpenAI Whisper, generates a structured title and Markdown body via an OpenAI chat model using the per-type preprocessing prompt, lets the operator review and edit, and finally creates a GitHub issue tagged with the type label. The pipeline is synchronous inside the HTTP request — there is no queue, no cron, no worker.

Authoritative sources:

- Mission, business goals, scope (in/out), success criteria, assumptions and risks: [`../spec/P1-ziele-rahmenbedingungen.md`](../spec/P1-ziele-rahmenbedingungen.md).
- End-to-end business process: [`../spec/F1-geschaeftsprozesse.md`](../spec/F1-geschaeftsprozesse.md).
- Use cases (UC-01 … UC-12): [`../spec/F2-anwendungsfaelle.md`](../spec/F2-anwendungsfaelle.md).
- Application functions: [`../spec/F3-anwendungsfunktionen.md`](../spec/F3-anwendungsfunktionen.md).
- Non-functional requirements (Volere 10–17, with fit criteria): [`../spec/N1-nichtfunktional.md`](../spec/N1-nichtfunktional.md).

---

## 1.2 Quality Goals

The top quality goals that drive architectural decisions. Each goal links the originating business goal or constraint in the spec to the architectural realisation that follows in later chapters. Detailed NFRs with fit criteria are in [`../spec/N1-nichtfunktional.md`](../spec/N1-nichtfunktional.md); architectural decisions in [`A09-architecture-decisions.md`](A09-architecture-decisions.md).

| ID | Quality Goal | ISO 25010 category | Scenario / Measure | Driven by |
|----|--------------|---------------------|---------------------|-----------|
| QG-01 | **Low operational footprint** — single deployable on standard shared hosting; no queue, no cron, no container runtime in production. | Maintainability / Portability | The application can be deployed by uploading build artefacts via FTP and operates correctly without scheduled jobs or background workers. | [G-03](../spec/P1-ziele-rahmenbedingungen.md#p12-business-goals), [CON-3b-01](../spec/P1-constraints.md#con-3b-01-shared-hosting-production), [NFR-13b-01](../spec/N1-nichtfunktional.md#13b-expected-technological-environment), [ADR-002](A09-architecture-decisions.md) |
| QG-02 | **Synchronous responsiveness** — end-to-end latency dominated by external API calls, with no additional polling or queue delay. | Performance Efficiency | After "Process", the response (or an error) returns within 30 s for a note of up to ~2 minutes of audio; a loading indicator is visible throughout. | [SC-02](../spec/P1-ziele-rahmenbedingungen.md#p16-success-criteria), [NFR-12a-01](../spec/N1-nichtfunktional.md#12a-speed-and-latency-requirements), [NFR-12d-01](../spec/N1-nichtfunktional.md#12d-reliability-and-availability-requirements) |
| QG-03 | **Configuration-driven extensibility for message types** — adding a new message type is a config change, not a code change. | Maintainability | For a `MessageTypeDT` value already declared at spec level, label, icon, GitHub label, and preprocessing prompt are each adjustable in host configuration only, without editing application code. | [G-04](../spec/P1-ziele-rahmenbedingungen.md#p12-business-goals), [SC-03](../spec/P1-ziele-rahmenbedingungen.md#p16-success-criteria), [NFR-14a-01](../spec/N1-nichtfunktional.md#14a-maintenance-requirements), [N2](../spec/N2-querschnittskonzepte.md) |
| QG-04 | **AI provider portability** — replacing the AI provider is a local change confined to the integration point per neighbour. | Portability | Replacing OpenAI with another provider requires editing only the integration point for transcription and for content generation, plus host configuration. | [NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements) |
| QG-05 | **Single-user security** — two-factor browser authentication, server-only secrets, sanitisation of untrusted content at every dispatch boundary. | Security | Access is denied without both factors; no external API credential is observable in the browser; injected markup in transcripts appears inert in the dispatched issue. | [NFR-15a-01](../spec/N1-nichtfunktional.md#15a-access-requirements), [NFR-15b-01](../spec/N1-nichtfunktional.md#15b-integrity-requirements), [NFR-15b-04](../spec/N1-nichtfunktional.md#15b-integrity-requirements), [N2.4](../spec/N2-querschnittskonzepte.md), [N2.7](../spec/N2-querschnittskonzepte.md), [N2.8](../spec/N2-querschnittskonzepte.md) |

QG-01 and QG-02 are coupled: low footprint precludes a queue or scheduler, which in turn forces synchronous processing. The trade-off — a long-running HTTP request — is accepted because the operator is the sole user and the pipeline is bounded by external API latency (see [ADR-002](A09-architecture-decisions.md)).

---

## 1.3 Stakeholders

| Role | Reach | Architectural expectations |
|------|-------|-----------------------------|
| **Operator** | Sole human user (you). | A working voice-to-issue path from any device; ≤ 3 taps to start recording; reliable, surfaced failure modes; recoverable lockout without privileged shell access. |
| **Local AI agents** (Claude Code, OpenCode, …) | Indirect, via the GitHub Issues neighbour. | Well-formed, type-labelled GitHub issues with sanitised content and a clear boundary between operator-derived content and application-generated structure. No agent-facing API from Herold. |
| **Hosting provider** | External; shared hosting account. | The runtime stack required by the architecture (Apache, PHP), HTTPS at the public edge, out-of-band file-store write access (e.g. FTP) for deployment and recovery, no dependency on cron or background workers. |
| **External neighbouring systems** (OpenAI, GitHub) | External REST APIs called synchronously from the pipeline. | Authenticated outbound calls only; rate-limit awareness; idempotent retries from the operator on transient failure. |

Detailed stakeholder descriptions and constraints: [`../spec/P1-ziele-rahmenbedingungen.md#p13-stakeholders-and-users`](../spec/P1-ziele-rahmenbedingungen.md#p13-stakeholders-and-users) and the constraints annex [`../spec/P1-constraints.md`](../spec/P1-constraints.md).
