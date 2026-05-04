# F2 — Use Cases

Use cases in the sense of Siedersleben (chapter 4.4): concrete interaction scenarios between an operator and the system, each pursuing a single operator-meaningful goal that ends in a stable state. F2 is the **system-supported subset** of the business process described in F1: every activity in F1.1.2 that involves operator interaction with Herold appears here as a use case; system-internal steps (e.g. transcription, generation) are not use cases — they live in F3 as application functions.

Each use case is described with a tabular specification template adopted from Pohl & Rupp (2021), *Basiswissen Requirements Engineering* (5th ed., dpunkt.verlag). Attributes are filled where meaningful information can be stated; sections such as *Authors*, *Priority*, *Criticality*, *Source*, and *Responsible Stakeholder* are omitted because they would carry no discriminating value across the use cases of this single-operator system. Cross-references into F1, F3, N1, and the data model are recorded under *Qualities* and within the scenario steps. The numbering is stable; once a UC ID is referenced from another block, it is not renumbered.

---

## F2.1 Use Case Index

| ID | Use Case | Group | Maps to F1 activity |
|----|----------|-------|---------------------|
| UC-01 | Sign in | Access | — (precondition for A2) |
| UC-02 | Enrol second factor | Access | — (one-time setup) |
| UC-03 | Recover access | Access | — (recovery branch) |
| UC-04 | Sign out | Access | — (post-process) |
| UC-05 | Capture voice note | Note flow | A2 + A3 |
| UC-06 | Process voice note | Note flow | A4 (orchestrates A5–A6) |
| UC-07 | Edit generated content | Note flow | A7 |
| UC-08 | Dispatch voice note | Note flow | A8 |
| UC-09 | Browse voice notes | Management | — (cross-cutting) |
| UC-10 | View a voice note | Management | — (cross-cutting) |
| UC-11 | Delete a voice note | Management | — (cross-cutting) |
| UC-12 | View settings | Configuration | — (auxiliary) |

---

## F2.2 Access

### UC-01 — Sign in

| Section | Content |
|---------|---------|
| **Identifier** | UC-01 |
| **Name** | Sign in |
| **Description** | Operator authenticates with both factors and receives a session valid for any further use case. |
| **Trigger** | Operator opens Herold without an active session. |
| **Actors** | Operator (primary). |
| **Precondition** | TOTP has been enrolled (UC-02). The operator possesses both factors: the registered authenticator and the time-based code source. |
| **Postcondition** | Authenticated session active. Operator may proceed with any other UC. |
| **Main scenario** | 1. System presents the sign-in screen.<br>2. Operator presents the first factor.<br>3. System verifies the first factor.<br>4. System prompts for the time-based one-time password.<br>5. Operator enters the current code.<br>6. System verifies the code and establishes an authenticated session.<br>7. Operator is taken to the dashboard. |
| **Alternative scenarios** | *Session expired during the flow:* operator is sent back to step 1. |
| **Exception scenarios** | *First factor fails:* operator may retry within the rate limit; no session is established.<br>*TOTP code fails:* operator may retry within the same rate limit, or pivot to UC-03. |
| **Qualities** | [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*. |

### UC-02 — Enrol second factor

| Section | Content |
|---------|---------|
| **Identifier** | UC-02 |
| **Name** | Enrol second factor |
| **Description** | Operator binds a TOTP secret to the account and receives one-time recovery codes. |
| **Trigger** | Initial setup, or follow-up to UC-03 after a recovery. |
| **Actors** | Operator (primary). |
| **Precondition** | Operator has authenticated the first factor; no TOTP secret is currently bound to the account, or a recovery flow has invalidated the previous one. |
| **Postcondition** | TOTP is enrolled; recovery codes have been issued; UC-01 can succeed. |
| **Result** | Fresh TOTP secret bound to the account; set of one-time recovery codes shown to the operator. |
| **Main scenario** | 1. System generates a fresh TOTP secret and a set of recovery codes.<br>2. System displays the secret as a scannable code together with the recovery codes.<br>3. Operator captures the secret in an authenticator app and stores the recovery codes safely.<br>4. Operator enters a confirmation code generated from the new secret.<br>5. System verifies the confirmation code and persists the secret. |
| **Exception scenarios** | *Confirmation code wrong:* operator retries; the secret is not yet bound.<br>*Operator abandons setup before confirming:* the secret is discarded. |

### UC-03 — Recover access

| Section | Content |
|---------|---------|
| **Identifier** | UC-03 |
| **Name** | Recover access |
| **Description** | Operator regains a session by spending an unused recovery code; the bound TOTP is invalidated and must be re-enrolled. |
| **Trigger** | Operator cannot produce a TOTP code (lost device, app reset). |
| **Actors** | Operator (primary). |
| **Precondition** | Operator possesses an unused recovery code issued in UC-02; the first factor is still available. |
| **Postcondition** | Authenticated session active. TOTP unbound; one recovery code consumed. UC-02 must be run again before normal use resumes. |
| **Main scenario** | 1. Operator selects the recovery option from the sign-in screen.<br>2. Operator enters a recovery code.<br>3. System verifies the code, marks it consumed, and invalidates the bound TOTP secret.<br>4. System establishes an authenticated session.<br>5. Operator is required to run UC-02 again before resuming normal use. |
| **Exception scenarios** | *Invalid recovery code:* retry within the rate limit. |
| **Qualities** | [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*; [NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry* (60-minute time-to-live on the recovery channel). |

### UC-04 — Sign out

| Section | Content |
|---------|---------|
| **Identifier** | UC-04 |
| **Name** | Sign out |
| **Description** | Operator ends the active session. |
| **Trigger** | Operator chooses to end the session. |
| **Actors** | Operator (primary). |
| **Precondition** | Authenticated session. |
| **Postcondition** | No authenticated session. |
| **Main scenario** | 1. Operator triggers sign-out.<br>2. System invalidates the session.<br>3. Operator is returned to the sign-in screen. |

---

## F2.3 Note Flow

The four use cases in this group form the supported segment of the business process from F1.1.2 (activities A2–A8). Each UC ends in a stable note status (`recorded`, `processed`, or `sent`); the operator may pause between UCs for any duration.

### UC-05 — Capture voice note

| Section | Content |
|---------|---------|
| **Identifier** | UC-05 |
| **Name** | Capture voice note |
| **Description** | Operator records an audio note in the browser, choosing a message type and supplying any required extra fields, and submits it for later processing. |
| **Trigger** | Operator wants to capture an idea, task, or observation by voice. |
| **Actors** | Operator (primary); browser/microphone (supporting). |
| **Precondition** | Authenticated session; at least one configured message type is available. |
| **Postcondition** | Voice note exists at status `recorded`; audio document held in the local audio store. |
| **Result** | New note record at status `recorded` with attached audio document and the selected message type. |
| **Main scenario** | 1. Operator opens the recording screen.<br>2. Operator selects a message type.<br>3. System reveals any extra input fields the type schema declares (per AF-04).<br>4. Operator fills the required extra fields.<br>5. Operator records an audio note in the browser.<br>6. Operator submits the recording.<br>7. System validates the operator input against the type schema (AF-08) and the audio against the upload constraints in [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*.<br>8. System persists the audio document and a new note record, transitioning it to status `recorded` (AF-06). |
| **Alternative scenarios** | *Operator cancels before submitting:* nothing is persisted. |
| **Exception scenarios** | *Type-specific validation fails:* the offending fields are flagged; operator corrects and resubmits.<br>*Microphone access is denied by the browser:* operator is informed; no note is created. |
| **Qualities** | [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*; [NFR-13a-01](N1-nichtfunktional.md) *Mobile Usage on the Go*. |

### UC-06 — Process voice note

| Section | Content |
|---------|---------|
| **Identifier** | UC-06 |
| **Name** | Process voice note |
| **Description** | System turns a recorded audio note into structured content (title, body, optional extra fields) within a single synchronous request. |
| **Trigger** | Operator triggers processing on the note. |
| **Actors** | Operator (primary); OpenAI Whisper API (supporting); OpenAI Chat Completion API (supporting). |
| **Precondition** | A voice note exists at status `recorded`. |
| **Postcondition** | Note at status `processed` with structured content (title, body, optional extra fields) attached. |
| **Result** | Persisted structured content attached to the note. The transcript itself is not retained. |
| **Main scenario** | 1. Operator triggers processing. The whole scenario runs synchronously inside the HTTP request per [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*.<br>2. System transcribes the audio (AF-01).<br>3. System resolves the message type (AF-04) and generates structured content from the transcript (AF-02).<br>4. System sanitises the generated markdown (AF-03) and persists the structured content.<br>5. System transitions the note to status `processed` (AF-06). |
| **Alternative scenarios** | *Operator leaves the page during processing:* the synchronous request continues; the operator can return and observe the result. |
| **Exception scenarios** | *Transcription fails:* the note remains `recorded`; the operator is informed and may retry per [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*.<br>*Content generation fails:* same as above. |
| **Qualities** | [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*; [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*. |

### UC-07 — Edit generated content

| Section | Content |
|---------|---------|
| **Identifier** | UC-07 |
| **Name** | Edit generated content |
| **Description** | Operator refines or corrects the system-generated content of a processed note before dispatching it. |
| **Trigger** | Operator wants to refine or correct the system-generated content before dispatching, or simply revise it without dispatching now. |
| **Actors** | Operator (primary). |
| **Precondition** | Note at status `processed`. |
| **Postcondition** | Note still at status `processed`; content reflects the edits. UC-07 can be repeated. |
| **Main scenario** | 1. Operator opens the note's detail view (see UC-10).<br>2. Operator edits the title, body, or extra fields.<br>3. Operator saves.<br>4. System sanitises the edited markdown (AF-03) per [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*, revalidates the extra fields against the type schema (AF-08), and persists the changes. |
| **Alternative scenarios** | *Operator leaves without saving:* changes are discarded. |
| **Exception scenarios** | *Validation fails:* operator is shown the offending fields and corrects them. |
| **Qualities** | [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*. |

### UC-08 — Dispatch voice note

| Section | Content |
|---------|---------|
| **Identifier** | UC-08 |
| **Name** | Dispatch voice note |
| **Description** | System composes a GitHub issue from the note and pushes it to the configured repository, then records the issue reference. |
| **Trigger** | Operator decides the note is ready to leave Herold. |
| **Actors** | Operator (primary); GitHub Issues (supporting). |
| **Precondition** | Note at status `processed`. |
| **Postcondition** | Note at status `sent`; issue reference stored. The downstream consumer ecosystem (F1.1.1) takes over. |
| **Result** | New GitHub issue in the configured repository, labelled per the message type; issue reference (number, URL) persisted with the note. |
| **Main scenario** | 1. Operator triggers dispatch.<br>2. System composes a GitHub issue from the note (AF-05) using the type-resolved label (AF-04) and the sanitised content.<br>3. System pushes the issue to the configured GitHub repository.<br>4. System records the resulting issue reference against the note and transitions it to status `sent` (AF-06). |
| **Exception scenarios** | *GitHub returns an error:* the note remains `processed`; the operator is informed and may retry per [NFR-12d-01](N1-nichtfunktional.md).<br>*Network error mid-dispatch:* same as above; the system does not assume the issue was created. |
| **Qualities** | [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*; [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*; [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*. |

---

## F2.4 Management

### UC-09 — Browse voice notes

| Section | Content |
|---------|---------|
| **Identifier** | UC-09 |
| **Name** | Browse voice notes |
| **Description** | Operator gets an overview of past and pending notes, ordered by recency. |
| **Trigger** | Operator wants an overview of past and pending notes. |
| **Actors** | Operator (primary). |
| **Precondition** | Authenticated session. |
| **Postcondition** | No state change. |
| **Main scenario** | 1. Operator opens the notes list.<br>2. System renders the notes ordered by recency, showing status, message type, timestamp, and a short summary.<br>3. Operator scans the list and may apply filters available on the screen. |
| **Alternative scenarios** | *Empty list:* an empty-state message is shown. |

### UC-10 — View a voice note

| Section | Content |
|---------|---------|
| **Identifier** | UC-10 |
| **Name** | View a voice note |
| **Description** | Operator inspects a single note: status, type, timestamps, structured content, issue reference; may stream the audio recording. |
| **Trigger** | Operator selects a note from the list, or navigates directly to it. |
| **Actors** | Operator (primary). |
| **Precondition** | Authenticated session; the selected note exists. |
| **Postcondition** | No state change. UC-10 is a precondition for UC-07 and a typical entry point for UC-06, UC-08, and UC-11. |
| **Main scenario** | 1. System renders the note's detail view: status, type, timestamps, structured content (if any), issue reference (if `sent`).<br>2. Operator may stream the audio recording. The audio is retained for the lifetime of the note record and only removed via UC-11. |

### UC-11 — Delete a voice note

| Section | Content |
|---------|---------|
| **Identifier** | UC-11 |
| **Name** | Delete a voice note |
| **Description** | Operator irreversibly removes a note record and its audio document; any dispatched GitHub issue is left untouched. |
| **Trigger** | Operator wants the note gone. |
| **Actors** | Operator (primary). |
| **Precondition** | Authenticated session; the selected note exists, in any status. |
| **Postcondition** | Note and its audio document are gone. The dispatched GitHub issue (if any) is **not** removed — that is outside Herold's scope (F1.3; P1 non-goal [NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle*). |
| **Main scenario** | 1. Operator triggers delete from the detail view.<br>2. System asks the operator to confirm, since the action is irreversible.<br>3. Operator confirms.<br>4. System removes the note record and any retained audio document. |
| **Alternative scenarios** | *Operator cancels at the confirmation step:* nothing changes. |

---

## F2.5 Configuration

### UC-12 — View settings

| Section | Content |
|---------|---------|
| **Identifier** | UC-12 |
| **Name** | View settings |
| **Description** | Operator inspects the active read-only configuration (configured message types, target GitHub repository, active OpenAI model identifier). |
| **Trigger** | Operator wants to inspect the active configuration. |
| **Actors** | Operator (primary). |
| **Precondition** | Authenticated session. |
| **Postcondition** | No state change. |
| **Main scenario** | 1. Operator opens the settings screen.<br>2. System renders the active configuration in a read-only form. |

> Settings are read-only in Herold. Configuration changes are made out-of-band on the host (P1 constraints, S3 deployment). If a future revision makes settings writable, a new UC *Update settings* will be added with a clearly defined audit trail.

---

## F2.6 Out of Scope for F2

- **Transcription, content generation, markdown sanitisation, message-type resolution.** These are system-internal steps with no operator decision point — F3 functions AF-01 to AF-04.
- **Streaming the audio recording.** Step inside UC-10, not a goal in itself.
- **Re-process and re-dispatch on failure.** Operator simply repeats UC-06 or UC-08; the status machine (AF-06) makes retries safe.
- **Issue triage, labelling beyond the type label, comments, or closure on the GitHub side.** Outside Herold (F1.3; P1 non-goal [NG-03](P1-ziele-rahmenbedingungen.md)).
- **Scheduled jobs, background workers, batch processing.** Herold has none (B2 not applicable; [ADR-002](../arch/ARCHITECTURE_DECISIONS.md); P1 non-goal [NG-04](P1-ziele-rahmenbedingungen.md) *Asynchronous processing*).
- **Multi-operator collaboration.** Forbidden by [CON-3a-04](P1-constraints.md) *Single-User System*.

---

## F2.7 Cross-references

| Block | Relevance to F2 |
|-------|-----------------|
| [F1](F1-geschaeftsprozesse.md) | Activities A2–A8 are realised by UC-05 to UC-08; access UCs bracket the process. |
| [F3](F3-anwendungsfunktionen.md) | Application functions AF-01 to AF-08 are invoked from the UCs as listed in their main scenarios. |
| D1 (planned) | Voice note record, status enum (`recorded → processed → sent`), issue reference, message-type metadata are referenced throughout. |
| [B1] (planned) | Screen designs and dialogue flow for each UC. |
| [N1](N1-nichtfunktional.md) | Latency budget for UC-06 and UC-08 ([NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*); error handling on retry ([NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*); rate limiting for UC-01, UC-03 ([NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*); audio upload constraints for UC-05 ([NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*); recovery token expiry for UC-03 ([NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry*); content sanitisation for UC-08 ([NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*). |
| [N2] (planned) | Authentication and TOTP handling underpin UC-01 to UC-04. |
| [S1] (planned) | OpenAI and GitHub interface contracts consumed by UC-06 and UC-08. |
| [E2](E2-glossar.md) | Definitions for *message type*, *recovery code*, *fine-grained PAT*, *voice note*. |
