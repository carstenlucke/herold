# F3 — Application Functions

Application functions in the sense of Siedersleben (chapter 4.4): atomic, reusable units of behaviour that are invoked from one or more use cases (F2) and carry **enough algorithmic substance from the operator's perspective** to warrant a separate description outside the use case. F3 deliberately does **not** describe screens, controllers, or storage; those belong in [B1](B1-dialogspezifikation.md), [F2](F2-anwendungsfaelle.md), and the architecture documentation respectively.

Following the Siedersleben criterion, F3 is reserved for genuine application algorithms. Concerns that **do not** belong in F3 are catalogued in their primary blocks:

- **Calls into neighbouring systems** (transcription, content generation, issue dispatch) — see [S1](S1-nachbarsysteme.md). The algorithm in those cases lives outside Herold.
- **Status state machines and entity-lifecycle invariants** — see [D1](D1-datenmodell.md) and [D2](D2-datentypen.md). The status machine for `VoiceNote` is [D2.5](D2-datentypen.md#d25-notestatusdt); the audio document lifecycle is captured under [D1.1 *VoiceNote*](D1-datenmodell.md#voicenote).
- **Type-driven behaviour and input validation** — see [D2.4](D2-datentypen.md#d24-messagetypedt), [D2.7](D2-datentypen.md#d27-typespecificdata), and [N2](N2-querschnittskonzepte.md) *Type-driven configuration* / *Validation*.
- **Pure CRUD on the application data store** — implementation, not specification.

What remains is one function — markdown sanitisation — which is genuinely algorithmic, runs locally, and is reused across multiple use cases.

---

## F3.1 Function Catalogue

| ID | Function | Purpose |
|----|----------|---------|
| [AF-03](#af-03--markdown-sanitisation) | Markdown Sanitisation | Reduce arbitrary markdown to a safe subset suitable for persistence and downstream dispatch. |

The earlier identifiers AF-01, AF-02, AF-04, AF-05, AF-06, AF-07 and AF-08 have been retired and their substance moved to S1, D1/D2, or N2 as listed in the introduction; AF-03 keeps its identifier to avoid breaking inbound references (NFR-15b-04).

---

## F3.2 Function Description

### AF-03 — Markdown Sanitisation

| Section | Content |
|---------|---------|
| **Purpose** | Reduce arbitrary markdown to a safe, predictable subset before it is stored or dispatched. |
| **Inputs** | Raw markdown string. |
| **Outputs** | Sanitised markdown string. |
| **Rules and invariants** | - Removes constructs that can carry executable or rendering side-effects (script content, raw HTML beyond an allowlist, javascript URLs, etc.), as required by [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization*.<br>- Idempotent: applying the function to its own output yields the same output.<br>- Preserves operator intent for the legitimate subset (headings, lists, links, code blocks, emphasis). |
| **Used by** | [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note) *Process voice note* (sanitises generated content); [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content) *Edit generated content* (sanitises operator edits); [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note) *Dispatch voice note* (sanitises content immediately before push via [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api)). |

---

## F3.3 Out of Scope for F3

- **Authentication and 2FA verification.** Cross-cutting concern; see [N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session) and the relevant NFRs in [N1](N1-nichtfunktional.md).
- **Calls into neighbouring systems.** Transcription, content generation, and GitHub dispatch live in [S1](S1-nachbarsysteme.md), not here. The algorithm in those cases is provided by the third-party system.
- **Status transitions and entity lifecycles.** The `VoiceNote` status machine ([D2.5](D2-datentypen.md#d25-notestatusdt)) and the audio-document lifecycle ([D1.1 *VoiceNote*](D1-datenmodell.md#voicenote)) are entity invariants, not application functions.
- **Type-driven configuration resolution and per-type input validation.** Cross-cutting concepts; see [N2.2](N2-querschnittskonzepte.md#n22-type-driven-configuration) and [N2.3](N2-querschnittskonzepte.md#n23-validation), backed by [D2.4](D2-datentypen.md#d24-messagetypedt) and [D2.7](D2-datentypen.md#d27-typespecificdata).
- **Persistence operations.** Loading and storing voice note records, status updates as a database operation, and audio file I/O are implementation concerns.
- **Rendering, navigation, screen layout.** Belong in [B1](B1-dialogspezifikation.md).
- **HTTP route handling, controllers, middleware.** Implementation, not specification.

---

## F3.4 Cross-references

| Block | Relevance to F3 |
|-------|-----------------|
| [F1](F1-geschaeftsprozesse.md) | Activity A7 motivates AF-03 (operator review of generated content before dispatch). |
| [F2](F2-anwendungsfaelle.md) | [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content), [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note) invoke AF-03. |
| [S1](S1-nachbarsysteme.md) | AF-03 sanitises content produced by [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api) before it is dispatched via [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api). |
| [N1](N1-nichtfunktional.md) | [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization* is the binding requirement on AF-03. |
| [N2](N2-querschnittskonzepte.md) | [N2.7](N2-querschnittskonzepte.md#n27-content-sanitisation) is the cross-cutting strategy that schedules AF-03 at every persistence and dispatch boundary. |
| [E2](E2-glossar.md) | Definitions for *voice note*, *message type*. |
