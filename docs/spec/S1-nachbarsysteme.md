# S1 — Neighbouring System Interfaces

S1 details the interface contracts between Herold and the neighbouring systems enumerated in [P2.2](P2-architekturueberblick.md#p22-neighbouring-systems). For each NB-XX entry that Herold actively communicates with, this block describes the **operations** Herold issues against the system, the **payloads** carried in each direction, and the **error semantics** the calling use case must observe.

S1 deliberately scopes to the **boundary** between Herold and each neighbour. It does not describe the surrounding pipeline (that is F2), the entities written into Herold's own data store (that is D1), nor the algorithm that prepares dispatched content (that is [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation)).

The system context below (reproduced from [P2.1](P2-architekturueberblick.md#p21-system-context)) places the neighbours detailed in this block at a glance:

![System Context — Herold](diagrams-png/p2-system-context.png)

The C4 context diagram is one level coarser than the S1 inventory. The mapping to S1 sections is:

- *Operator* + the inbound HTTPS edge → [S1.2](#s12--nb-01--operator-browser) *Operator browser* (the Person is the actor; the browser is the channel S1 contracts against).
- *OpenAI API* → split in S1 into [S1.3](#s13--nb-02--openai-whisper-api) *Whisper API* and [S1.4](#s14--nb-03--openai-chat-completion-api) *Chat Completion API*; the edge label "Audio → transcript; prompt → title + body" already foreshadows the split.
- *GitHub Issues* → [S1.5](#s15--nb-04--github-issues-api).
- *Local AI agents* → [S1.6](#s16--nb-05--local-ai-agents).

---

## S1.1 Conventions

The following conventions apply to every operation listed below.

- **Synchronous and blocking.** Every call against a neighbour is part of a single HTTP request from the operator's browser. The pipeline blocks on the response per [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*. Neither queues, nor background workers, nor scheduled retries exist.
- **Error propagation.** Provider failures (timeouts, 5xx, 4xx, network errors) propagate to the surrounding use case and are surfaced to the operator per [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*. The voice note's status does not advance on failure (see [D2.5](D2-datentypen.md#d25-notestatusdt) *NoteStatusDT*); `VoiceNote.errorMessage` is populated.
- **Authentication.** Every outbound call carries a host-configured credential supplied out-of-band (see [D2.6](D2-datentypen.md#d26-opaquesecret) *OpaqueSecret*). Credentials are never echoed into payloads, log lines, or operator-facing messages.
- **Payload-level details** (concrete endpoint URLs, request/response field names, codec/format specifics) are implementation concerns and live in `docs/arch/` and in code, not here. S1 declares the operations and their semantics; the architecture layer binds them to concrete endpoints.

---

## S1.2 NB-01 — Operator browser

The operator's browser is the sole human-facing channel. The interface is the dialogue surface specified in [B1](B1-dialogspezifikation.md); audio is captured in-browser and uploaded as part of the [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) submission. No further protocol-level contract needs to be specified here beyond what B1 already states.

---

## S1.3 NB-02 — OpenAI Whisper API

Speech-to-text on the audio document attached to a `VoiceNote` ([D1.1](D1-datenmodell.md#voicenote)).

| Aspect | Content |
|--------|---------|
| **Operation** | `transcribe(audio, languageHint?) → transcript` |
| **Direction** | Bidirectional (request: audio bytes; response: transcript text). |
| **Inputs** | The audio document referenced by `VoiceNote.audioPath`; an optional language hint. Format and size constraints at the upload boundary are governed by [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*. |
| **Outputs** | A plain-text transcript, written into `VoiceNote.transcript` by the calling use case. |
| **Triggered by** | [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note) Schritt 3. |
| **Semantics** | The transcript is preserved verbatim; no editorial transformation occurs at the boundary. The caller does not retain audio at the provider beyond the request. |
| **Failure handling** | Any provider error propagates to UC-06 per [NFR-12d-01](N1-nichtfunktional.md). The note remains `recorded`; the operator may retry. |

---

## S1.4 NB-03 — OpenAI Chat Completion API

Title and Markdown body generation from a transcript, parametrised by message type.

| Aspect | Content |
|--------|---------|
| **Operation** | `generate(transcript, prompt, extraFieldSchema?) → { title, body, extras? }` |
| **Direction** | Bidirectional (request: prompt + transcript; response: structured content). |
| **Inputs** | The transcript produced by [S1.3](#s13--nb-02--openai-whisper-api); the prompt and optional extra-field schema bound to the note's `MessageTypeDT` ([D2.4](D2-datentypen.md#d24-messagetypedt)). |
| **Outputs** | Title, Markdown body, and optionally the message-type-specific extras declared by [D2.7](D2-datentypen.md#d27-typespecificdata). The body is **not** sanitised at this stage — [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) must run before persistence and before dispatch. The operator may overwrite any field via [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content). |
| **Triggered by** | [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note) Schritt 4. |
| **Semantics** | All message-type knowledge is supplied through the resolution declared in [D2.4](D2-datentypen.md#d24-messagetypedt); message types are never hard-coded at this boundary. |
| **Failure handling** | Any provider error propagates to UC-06 per [NFR-12d-01](N1-nichtfunktional.md). The note remains `recorded`; the operator may retry. |

---

## S1.5 NB-04 — GitHub Issues API

One-way push of a reviewed voice note as a GitHub issue.

| Aspect | Content |
|--------|---------|
| **Operation** | `createIssue(repository, title, body, label) → { number, url }` |
| **Direction** | Outbound (response is consumed only to record the issue reference). |
| **Inputs** | The fixed target `GitHubRepository` ([D1.2](D1-datenmodell.md#githubrepository)); the note's title, sanitised body (per [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation), required by [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*); and exactly one label resolved from the note's `MessageTypeDT` ([D2.4](D2-datentypen.md#d24-messagetypedt)). |
| **Outputs** | The created issue's number and URL, written into `VoiceNote.githubIssueNumber` / `VoiceNote.githubIssueUrl` by the calling use case (see [D1.1](D1-datenmodell.md#voicenote)). |
| **Triggered by** | [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note) Schritt 4. |
| **Semantics** | One-way only. Herold does not read back issue state after creation; no synchronisation logic exists at this boundary (see P1 non-goal [NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle*). Exactly one type label is attached; further triage is the consumer's responsibility (F1.3). |
| **Failure handling** | Any provider error propagates to UC-08 per [NFR-12d-01](N1-nichtfunktional.md). The note remains `processed`; the operator may retry. The system does not assume the issue was created on a network error mid-call. |

---

## S1.6 NB-05 — Local AI agents

Local AI agents (Claude Code, OpenCode, …) are listed in P2.2 for completeness. They interact with [GitHub Issues](#s15--nb-04--github-issues-api) — reading dispatched tickets, commenting, closing — but never with Herold directly. Herold has no interface contract with the agents and exposes none.

---

## S1.7 Out of Scope for S1

- **Internal pipeline orchestration.** The order in which the operations above are invoked, the data they shuttle into `VoiceNote`, and the user-visible flow live in [F2](F2-anwendungsfaelle.md), not here.
- **Domain algorithms run inside Herold.** [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) *Markdown Sanitisation* is not a neighbour interaction; it runs locally on content produced by S1.4 before it is persisted or pushed via S1.5.
- **Endpoint URLs, payload field names, retry budgets, codec specifics.** Implementation concerns; see `docs/arch/` and code.
- **Operator-side dialogue.** The browser channel is specified in [B1](B1-dialogspezifikation.md).

---

## S1.8 Cross-references

| Block | Relevance to S1 |
|-------|-----------------|
| [P2](P2-architekturueberblick.md) | P2.2 enumerates the neighbouring systems; each NB-XX entry there is detailed in one S1 section. |
| [F2](F2-anwendungsfaelle.md) | [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note) invokes [S1.3](#s13--nb-02--openai-whisper-api) and [S1.4](#s14--nb-03--openai-chat-completion-api); [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note) invokes [S1.5](#s15--nb-04--github-issues-api). |
| [F3](F3-anwendungsfunktionen.md) | [AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) sanitises content produced by S1.4 before it is dispatched via S1.5. |
| [D1](D1-datenmodell.md) | S1.3 populates `VoiceNote.transcript`; S1.4 populates `VoiceNote.processedTitle` / `processedBody` / `metadata`; S1.5 populates `VoiceNote.githubIssueNumber` / `githubIssueUrl`. |
| [D2](D2-datentypen.md) | The prompt and label bound to a `MessageTypeDT` ([D2.4](D2-datentypen.md#d24-messagetypedt)) drive S1.4 and S1.5 respectively; the slot inventory in [D2.7](D2-datentypen.md#d27-typespecificdata) shapes the optional extras returned by S1.4. |
| [N1](N1-nichtfunktional.md) | [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing* governs blocking semantics; [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling* governs failure propagation; [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation* governs the audio handed to S1.3; [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization* governs the body handed to S1.5. |
| [P1](P1-ziele-rahmenbedingungen.md) | Non-goal [NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle* fixes S1.5 as one-way push. |
