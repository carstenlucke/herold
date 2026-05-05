# F3 — Application Functions

Application functions in the sense of Siedersleben (chapter 4.4): atomic, reusable units of behaviour that are invoked from one or more use cases (F2). Each function is described as an algorithm — inputs, outputs, rules, invariants — independently of UI or persistence concerns. F3 deliberately does **not** describe screens, controllers, or storage; those belong in B1, F2, and the architecture documentation respectively.

Functions in this block are reused across use cases or carry enough algorithmic substance to warrant a separate description. Pure CRUD on the application data store is not an application function and is therefore absent from F3.

---

## F3.1 Function Catalogue

| ID | Function | Purpose |
|----|----------|---------|
| [AF-01](#af-01--transcription) | Transcription | Convert a recorded audio document into a plain-text transcript. |
| [AF-02](#af-02--content-generation) | Content Generation | Derive structured note content (title, body, optional extra fields) from a transcript under a given message type. |
| [AF-03](#af-03--markdown-sanitisation) | Markdown Sanitisation | Reduce arbitrary markdown to a safe subset suitable for persistence and downstream dispatch. |
| [AF-04](#af-04--message-type-resolution) | Message Type Resolution | Resolve a message-type identifier to its prompt, optional extra-field schema, and outbound label. |
| [AF-05](#af-05--issue-composition--dispatch) | Issue Composition & Dispatch | Assemble a GitHub Issue from a note record and push it to the configured repository, recording the reference. |
| [AF-06](#af-06--status-transition) | Status Transition | Enforce the legal state machine for a voice note (`recorded → processed → sent`). |
| [AF-07](#af-07--audio-lifecycle) | Audio Lifecycle | Manage the audio document attached to a voice note from creation through removal. |
| [AF-08](#af-08--per-type-input-validation) | Per-Type Input Validation | Validate operator-supplied input against the schema declared by the selected message type. |

---

## F3.2 Function Descriptions

Each entry follows the same shape: **Purpose**, **Inputs**, **Outputs**, **Rules and invariants**, **Used by**. *Used by* references are tentative until F2 is written.

### AF-01 — Transcription

| Section | Content |
|---------|---------|
| **Purpose** | Produce a text transcript of an audio recording. |
| **Inputs** | Audio recording (referenced by the voice note record); language hint, if available. |
| **Outputs** | Plain-text transcript. |
| **Rules and invariants** | - The function is synchronous and blocks the surrounding request (per ADR-002 and [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*).<br>- The transcript is preserved verbatim; no editorial transformation occurs in this function.<br>- Failure of the upstream provider is propagated to the surrounding UC; the note's status does not advance, in line with [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*. |
| **Used by** | UC-06 *Process voice note*. |

### AF-02 — Content Generation

| Section | Content |
|---------|---------|
| **Purpose** | Turn a transcript into the structured note content the operator will review and dispatch. |
| **Inputs** | Transcript (AF-01 output); message type (resolved via AF-04); optional operator-supplied extra fields collected at recording time. |
| **Outputs** | Title, markdown body, and optional message-type-specific extra fields, in the shape declared by the type schema. |
| **Rules and invariants** | - The prompt used is selected exclusively via AF-04; this function does not embed type knowledge.<br>- The operator may overwrite any output field in the subsequent review step; this function does not lock results.<br>- Markdown produced here is **not** considered sanitised — AF-03 must run before persistence and before dispatch. |
| **Used by** | UC-06 *Process voice note*. |

### AF-03 — Markdown Sanitisation

| Section | Content |
|---------|---------|
| **Purpose** | Reduce arbitrary markdown to a safe, predictable subset before it is stored or dispatched. |
| **Inputs** | Raw markdown string. |
| **Outputs** | Sanitised markdown string. |
| **Rules and invariants** | - Removes constructs that can carry executable or rendering side-effects (script content, raw HTML beyond an allowlist, javascript URLs, etc.), as required by [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*.<br>- Idempotent: applying the function to its own output yields the same output.<br>- Preserves operator intent for the legitimate subset (headings, lists, links, code blocks, emphasis). |
| **Used by** | UC-06 *Process voice note* (sanitises generated content); UC-07 *Edit generated content* (sanitises operator edits); UC-08 *Dispatch voice note* (sanitises content immediately before push). |

### AF-04 — Message Type Resolution

| Section | Content |
|---------|---------|
| **Purpose** | Translate a message-type identifier into the parameters every other function and use case needs. |
| **Inputs** | Message-type identifier. |
| **Outputs** | Generation prompt, extra-field schema, GitHub label, and any other type-specific metadata. |
| **Rules and invariants** | - The set of valid identifiers is configurable; an unknown identifier is rejected.<br>- All consumers of message-type behaviour go through this function — message types are never hard-coded elsewhere in the application functions. |
| **Used by** | UC-05 *Capture voice note* (extra-field schema); UC-06 *Process voice note* (prompt selection); UC-08 *Dispatch voice note* (label selection). |

### AF-05 — Issue Composition & Dispatch

| Section | Content |
|---------|---------|
| **Purpose** | Push a reviewed note to GitHub as an issue and bind the resulting reference to the note record. |
| **Inputs** | Voice note record (with sanitised content, per AF-03); resolved message type (per AF-04). |
| **Outputs** | GitHub issue reference (number, URL) recorded against the note. |
| **Rules and invariants** | - One-way only. The function does not read back issue state after creation; no synchronisation logic lives here (per P1 non-goal [NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle*).<br>- Synchronous within the dispatch request ([NFR-12a-01](N1-nichtfunktional.md)). On success, the note transitions to `sent` via AF-06; on failure, the status is not advanced and the operator can retry per [NFR-12d-01](N1-nichtfunktional.md).<br>- The pushed body must already be sanitised (AF-03) per [NFR-15b-04](N1-nichtfunktional.md).<br>- Exactly one type label is attached; further triage is the consumer's responsibility (F1.3). |
| **Used by** | UC-08 *Dispatch voice note* (including its retry path on transient failure). |

### AF-06 — Status Transition

| Section | Content |
|---------|---------|
| **Purpose** | Centralise the legal state machine for a voice note. |
| **Inputs** | Current status; requested target status; context (which other function is requesting the transition). |
| **Outputs** | Updated note record, or rejection if the transition is illegal. |
| **Rules and invariants** | - Allowed transitions: `recorded → processed`, `processed → sent`. No other transitions, including no regressions.<br>- Each transition is the consequence of a successful operation in another function (AF-02 for `processed`, AF-05 for `sent`); status is never advanced speculatively. |
| **Used by** | UC-05 *Capture voice note* (initial `recorded`); UC-06 *Process voice note* (`recorded → processed`); UC-08 *Dispatch voice note* (`processed → sent`). |

### AF-07 — Audio Lifecycle

| Section | Content |
|---------|---------|
| **Purpose** | Manage the audio document tied to a voice note from creation through removal. |
| **Inputs** | Voice note record; the audio document submitted at recording time. |
| **Outputs** | Audio document persisted, served, or removed in step with the note's lifecycle. |
| **Rules and invariants** | - Audio is persisted alongside the note at recording time (UC-05) and bound to it.<br>- Audio is streamable for the entire lifetime of the note (UC-10); there is no scheduled pruning, no retention timer, and no `processed`-triggered cleanup. The applicable size and format constraints at upload are governed by [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*.<br>- Audio is removed together with the note record when the operator deletes the note (UC-11). Removal is irreversible.<br>- The note record may exist without an audio document only after deletion of the audio (which currently coincides with note deletion); audio never exists without a note. |
| **Used by** | UC-05 *Capture voice note* (persist); UC-10 *View a voice note* (stream); UC-11 *Delete a voice note* (remove). |

### AF-08 — Per-Type Input Validation

| Section | Content |
|---------|---------|
| **Purpose** | Enforce the schema a message type imposes on operator-supplied fields at recording time. |
| **Inputs** | Message-type identifier; raw operator input (extra fields beyond audio). |
| **Outputs** | Validated input, or a structured rejection naming the offending fields. |
| **Rules and invariants** | - The schema comes from AF-04; this function does not embed per-type knowledge.<br>- Validation is strict: missing required fields, wrong value ranges, or unknown fields all fail.<br>- The audio recording itself is not validated here beyond presence; format checks live closer to AF-01. |
| **Used by** | UC-05 *Capture voice note* (at submission); UC-07 *Edit generated content* (revalidates extra fields on save). |

---

## F3.3 Out of Scope for F3

- **Authentication and 2FA verification.** Treated as a cross-cutting concern in N2 rather than as an application function, because no application use case calls it as a step — it is a precondition enforced by the surrounding session.
- **Persistence operations.** Loading and storing voice note records, status updates as a database operation, and audio file I/O are implementation concerns; they appear in F3 only insofar as they are side-effects of an application function (e.g. AF-05 records the issue reference).
- **Rendering, navigation, screen layout.** Belong in B1.
- **HTTP route handling, controllers, middleware.** Implementation, not specification.

---

## F3.4 Cross-references

| Block | Relevance to F3 |
|-------|-----------------|
| [F1](F1-geschaeftsprozesse.md) | Activities A5, A6, A7, A8 motivate AF-01, AF-02, AF-03, AF-05. |
| [F2](F2-anwendungsfaelle.md) | Use cases UC-05 to UC-08 and UC-11 invoke the functions listed above; *Used by* lines name the calling UC. |
| D1 (planned) | Voice note record, status enum, issue reference, message-type metadata referenced throughout F3. |
| [N1](N1-nichtfunktional.md) | End-to-end latency budget for AF-01, AF-02, AF-05 ([NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*); error handling on AF-01/AF-02/AF-05 failure ([NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*); audio upload constraints for AF-07 ([NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*); content sanitisation in AF-03/AF-05 ([NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*). |
| S1 (planned) | Interface contracts for OpenAI (AF-01, AF-02) and GitHub (AF-05). |
| [E2](E2-glossar.md) | Definitions for *message type*, *fine-grained PAT*, *voice note*. |
