# N2 — Cross-cutting Concepts

Cross-cutting concepts in the sense of Siedersleben (chapter 4.7): unified strategies for concerns that affect the *behaviour or appearance of the system as a whole*, rather than belonging to a single function, entity, or use case. N2 records the strategy a recurring concern follows; details that are specific to a single function or entity remain at their primary site.

Every concept below is *referenced* — never *defined* — by at least two other blocks. N2 exists to keep those references coherent: changing the strategy of a cross-cutting concern is a single edit here, not a sweep across F2, S1, D1, D2, and N1.

---

## N2.1 Concept Catalogue

| ID | Concept | One-line summary |
|----|---------|------------------|
| [N2.2](#n22-type-driven-configuration) | Type-driven configuration | Single resolution point for per-`MessageTypeDT` bindings (slot inventory, prompt, label). |
| [N2.3](#n23-validation) | Validation | Strict schema-driven validation of operator input at the boundaries that accept it. |
| [N2.4](#n24-authentication-and-session) | Authentication and session | Two-factor browser auth, single `Operator`, out-of-band recovery; sole gate for every operator-facing use case. |
| [N2.5](#n25-failure-handling) | Failure handling | Synchronous; on neighbour failure the entity status does not advance, `errorMessage` is populated, the operator retries explicitly. |
| [N2.6](#n26-logging) | Logging | Structured operational and security event log; never carries secrets, transcripts, or operator-derived content. |
| [N2.7](#n27-content-sanitisation) | Content sanitisation | Operator-derived content is untrusted and is neutralised at every persistence and dispatch boundary. |
| [N2.8](#n28-secret-handling) | Secret handling | Secrets are server-only, never browser-bound, never logged, never round-tripped; verification is constant-time. |

The catalogue is closed at the spec level. Adding a cross-cutting concept is a spec change; tightening the strategy of an existing concept is the more common case.

---

## N2.2 Type-driven configuration

**Concern.** Herold supports a closed set of message types ([D2.4](D2-datentypen.md#d24-messagetypedt) *MessageTypeDT*). Several behaviours are bound per type — the slot inventory for extra fields, the generation prompt, the GitHub label. These bindings must be obtained through a single resolution point so that adding or changing a type touches exactly one configuration surface, and so that no consumer hard-codes message-type knowledge.

**Strategy.**

- The catalogue of valid identifiers is closed and lives in [D2.4](D2-datentypen.md#d24-messagetypedt). The slot inventory per identifier is fixed at spec level in [D2.7](D2-datentypen.md#d27-typespecificdata).
- The prompt and label per identifier are supplied by the host configuration out-of-band; they are not part of the application's persisted data ([D1](D1-datenmodell.md) does not model them).
- Every consumer ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api), [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings)) obtains its binding through this single resolution point. Unknown identifiers are rejected at the boundary that observes them.
- The split between the spec/code layer (catalogue + slot inventory) and the host-config layer (prompt + label + display attributes) is reinforced by [NFR-14a-01](N1-nichtfunktional.md) *Layered Message-Type Definition*.

**Cross-references.** [D2.4](D2-datentypen.md#d24-messagetypedt), [D2.7](D2-datentypen.md#d27-typespecificdata), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api), [NFR-14a-01](N1-nichtfunktional.md).

---

## N2.3 Validation

**Concern.** Operator-supplied input enters the system at two points: capture submission ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note)) and edit save ([UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content)). Both must apply the same rules to the same data shape ([D2.7](D2-datentypen.md#d27-typespecificdata) *TypeSpecificData*).

**Strategy.**

- Validation is *strict*: missing required slots, type-incorrect values, and unknown slot names all fail.
- Validation is *schema-driven*: the schema is the slot inventory declared for the bound `MessageTypeDT` in [D2.7](D2-datentypen.md#d27-typespecificdata).
- Validation is performed at the boundary that accepts the input. Nothing is persisted before the input is accepted; on rejection the offending fields are surfaced to the operator. The dialogue layer ([B1](B1-dialogspezifikation.md)) cites where each violation appears on screen — *how* it is computed lives here.
- The audio recording itself is not validated against this schema; its format and size constraints are governed by [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation* and apply at the upload boundary that feeds [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api).

**Cross-references.** [D2.7](D2-datentypen.md#d27-typespecificdata), [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content), [NFR-15a-03](N1-nichtfunktional.md), [B1](B1-dialogspezifikation.md).

---

## N2.4 Authentication and session

**Concern.** Browser access to every operator-facing use case other than the sign-in and recovery flows themselves must be gated by a verified operator identity. The gate must work uniformly across [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) through [UC-13](F2-anwendungsfaelle.md#uc-13--view-dashboard); a use case never re-implements its own check. Recovery from a lost factor must be possible without privileged shell access ([NFR-14a-02](N1-nichtfunktional.md)).

**Strategy.**

- *Two factors, one operator.* Authentication is API key + TOTP per [NFR-15a-01](N1-nichtfunktional.md). The single `Operator` ([D1.1](D1-datenmodell.md#operator)) carries both verifiers — `apiKeyHash` and `totpSecret` — and the marker `totpConfirmedAt`. There is at most one operator instance ([CON-3a-04](P1-constraints.md)).
- *Single gate.* A request that has not presented both factors and does not belong to the sign-in or recovery flows is rejected at the boundary, before any use case runs. The session, once established, attests both factors for its lifetime; sign-out ([UC-04](F2-anwendungsfaelle.md#uc-04--sign-out)) is the only operator-initiated way to retire it.
- *Out-of-band recovery, not email-based.* Recovery ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)) is mediated by the `RecoveryToken` ([D1.1](D1-datenmodell.md#recoverytoken)) — a single transient artefact placed by the operator into the host file store. Successful redemption replaces both factors and reveals the new API key exactly once. The token's time-to-live is governed by [NFR-15a-04](N1-nichtfunktional.md); the three rejection reasons (missing, mismatched, expired) are externally indistinguishable.
- *Rate limiting at the gate.* Sign-in and recovery enforce per-IP rate limits and lockouts per [NFR-15a-02](N1-nichtfunktional.md). The limit is applied at the same boundary that performs the credential check, before the credential check itself.
- *No second role.* Authorisation is degenerate: there is one role, with full access to all use cases. See [N2.9](#n29-out-of-scope-for-n2).

**Cross-references.** [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in)–[UC-04](F2-anwendungsfaelle.md#uc-04--sign-out), [D1.1 *Operator*](D1-datenmodell.md#operator), [D1.1 *RecoveryToken*](D1-datenmodell.md#recoverytoken), [NFR-15a-01](N1-nichtfunktional.md), [NFR-15a-02](N1-nichtfunktional.md), [NFR-15a-04](N1-nichtfunktional.md), [NFR-14a-02](N1-nichtfunktional.md), [CON-3a-04](P1-constraints.md).

---

## N2.5 Failure handling

**Concern.** The processing pipeline ([F1](F1-geschaeftsprozesse.md)) crosses three external services in succession ([S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api)) and runs synchronously inside the operator's HTTP request ([NFR-12a-01](N1-nichtfunktional.md)). Failures at any of the three boundaries must be handled the same way: visibly, without data loss, and without speculative state advancement.

**Strategy.**

- *Status does not advance on failure.* Allowed status transitions for `VoiceNote` are listed in [D2.5](D2-datentypen.md#d25-notestatusdt) *NoteStatusDT*. A transition fires only on the successful completion of the corresponding use case. Provider errors leave the status untouched.
- *Failure is recorded as data, not as state.* The failure reason is populated into `VoiceNote.errorMessage` ([D1.1](D1-datenmodell.md#voicenote)). `errorMessage` is an orthogonal failure flag, not a status value. A successful retry clears it.
- *Retry is an explicit operator action.* No automatic retries, no queues, no background workers ([NG-04](P1-ziele-rahmenbedingungen.md)). The operator re-invokes the same use case from the dialogue ([B1](B1-dialogspezifikation.md)).
- *Boundary errors propagate verbatim.* Errors at any of [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api) are treated identically: the use case surfaces the failure to the operator and the rules above apply. Mid-call network failure on [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api) is treated as a failure; the system does not assume the issue was created.
- *Validation rejection is not a failure.* Validation rejections ([N2.3](#n23-validation)) surface inline at the field level and do not populate `errorMessage`; nothing is persisted, so there is no failed state to flag.

**Cross-references.** [NFR-12a-01](N1-nichtfunktional.md), [NFR-12d-01](N1-nichtfunktional.md), [D2.5](D2-datentypen.md#d25-notestatusdt), [D1.1 *VoiceNote*](D1-datenmodell.md#voicenote), [S1.1](S1-nachbarsysteme.md#s11-conventions), [NG-04](P1-ziele-rahmenbedingungen.md).

---

## N2.6 Logging

**Concern.** The system needs an operational record of pipeline events and security-relevant events. The same log surface must accept entries from across the codebase without leaking secrets, transcripts, or operator-derived content. The log is the only persistent observability surface; there is no separate audit trail (see [N2.9](#n29-out-of-scope-for-n2)).

**Strategy.**

- *Two event categories.* Operational events (pipeline progress, neighbour-call outcomes, failure reasons) are keyed by `VoiceNote.id` ([D1.1](D1-datenmodell.md#voicenote)). Security events (authentication outcomes, rate-limit triggers, recovery-token rejections per [NFR-15a-04](N1-nichtfunktional.md)) carry source IP and timestamp and are emitted at the gate described in [N2.4](#n24-authentication-and-session).
- *Reference, never embed.* An operational entry references a `VoiceNote` by its `id`; it does not embed the `transcript`, the generated content, or the captured audio. Diagnostic depth is achieved through the entity itself, not through the log.
- *Redaction is a hard rule.* The framework's signing key, the operator's API key, the operator's TOTP secret, third-party API tokens (OpenAI, GitHub), and any session or bearer token must be masked before the entry leaves the application. This is the binding requirement [NFR-15b-03](N1-nichtfunktional.md) *Secret Redaction in Logs* and is enforced uniformly, regardless of the call site.
- *No operator-derived content.* The transcript, the generated title and body, the per-`MessageTypeDT` slot values, and the operator's edits never appear in log entries. They are *referenced* by `VoiceNote.id`.
- *No personal-data trail.* Source IP appears only on security-event entries (failed sign-in, lockout, recovery-token rejection); operational entries do not carry it.

**Cross-references.** [NFR-15b-03](N1-nichtfunktional.md), [NFR-15a-04](N1-nichtfunktional.md), [D1.1 *VoiceNote*](D1-datenmodell.md#voicenote), [D2.6](D2-datentypen.md#d26-opaquesecret).

---

## N2.7 Content sanitisation

**Concern.** Three content sources are *untrusted* from the system's perspective: the speech-to-text transcript ([S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)), the generated title and body ([S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api)), and the operator's manual edits ([UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content)). All three flow into `VoiceNote.processedBody` ([D1.1](D1-datenmodell.md#voicenote)) and onward to the dispatched issue ([S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api)), where downstream readers — humans and AI agents alike — may render or follow what they find.

**Strategy.**

- *One sanitisation function, several invocation sites.* The algorithmic core is [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) *Markdown Sanitisation*, idempotent and reusable. The cross-cutting rule is *when* it must run.
- *Run at every persistence and dispatch boundary.* Generated content is sanitised before persistence ([UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note)); operator edits are sanitised before persistence ([UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content)); the dispatched body is sanitised again immediately before push ([UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note)). Idempotence makes the repeated invocation safe.
- *Visual delimitation in the dispatched body.* Per [NFR-15b-04](N1-nichtfunktional.md) the dispatched issue body must visibly separate operator-derived content from application-generated structure, so a downstream reader can distinguish the two even where the content survives sanitisation.
- *Prompts are not content.* The configured preprocessing prompt for any `MessageTypeDT` value never appears in the dispatched body and never reaches the browser ([NFR-15b-02](N1-nichtfunktional.md)). Prompt material and operator-derived content are kept on different surfaces throughout.

**Cross-references.** [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation), [NFR-15b-04](N1-nichtfunktional.md), [NFR-15b-02](N1-nichtfunktional.md), [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content), [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note), [D1.1 *VoiceNote*](D1-datenmodell.md#voicenote).

---

## N2.8 Secret handling

**Concern.** Secret material exists at four sites: the operator's API key (held as a hash in `Operator.apiKeyHash`), the operator's TOTP secret (`Operator.totpSecret`), the recovery token (`RecoveryToken.token`), and the host-supplied third-party credentials (OpenAI, GitHub) that travel with every outbound call from [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api). Each must be handled identically: present where it is needed, absent everywhere else.

**Strategy.**

- *One type tag, one set of rules.* Every secret is typed as `OpaqueSecret` ([D2.6](D2-datentypen.md#d26-opaquesecret)). The handling rules — never rendered, never logged, never round-tripped, constant-time verification — attach to the type, not to the call site. A consumer that holds an `OpaqueSecret` cannot opt out.
- *Server-only.* External API credentials never leave the server ([NFR-15b-01](N1-nichtfunktional.md)). The browser never observes them in any request or response.
- *Configured prompts are not secrets, but follow the same channel.* Per-`MessageTypeDT` preprocessing prompts must not be transmitted to the browser ([NFR-15b-02](N1-nichtfunktional.md)). [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings) surfaces only the operator-pickable attributes (label, icon, GitHub label, slot schema).
- *Verification, not retrieval.* The operator's API key is presented at sign-in and verified against the stored hash; it is never retrieved. The TOTP secret is enrolled once and verified per code; the raw value is never read back. Recovery flows *replace*, never *reveal* — the only exception is the freshly minted API key shown exactly once at the end of [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access).
- *Constant-time comparison.* Any equality check that decides authentication or recovery success runs in constant time, on the byte representation of the value ([D2.6](D2-datentypen.md#d26-opaquesecret), [NFR-15a-01](N1-nichtfunktional.md)).
- *Redacted in logs.* Every secret passes through the redaction mechanism described in [N2.6](#n26-logging) before any log entry leaves the application.

**Cross-references.** [D2.6](D2-datentypen.md#d26-opaquesecret), [D1.1 *Operator*](D1-datenmodell.md#operator), [D1.1 *RecoveryToken*](D1-datenmodell.md#recoverytoken), [NFR-15b-01](N1-nichtfunktional.md), [NFR-15b-02](N1-nichtfunktional.md), [NFR-15b-03](N1-nichtfunktional.md), [NFR-15a-01](N1-nichtfunktional.md), [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings), [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access).

---

## N2.9 Out of Scope for N2

Several cross-cutting concerns named as canonical examples by Siedersleben are deliberately not concepts in this system:

- **Multi-tenancy.** Herold is a single-operator system ([CON-3a-04](P1-constraints.md), [NG-01](P1-ziele-rahmenbedingungen.md)). All data is owned by one `Operator`; no entity carries a tenant discriminator.
- **Authorisation / role concept.** Single role; every authenticated request has access to every use case. The authentication gate ([N2.4](#n24-authentication-and-session)) is the only access control. Multi-user roles and permissions are explicitly out of scope ([NG-01](P1-ziele-rahmenbedingungen.md)).
- **Historisation / audit trail.** The system does not retain prior versions of any field. Edits in [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content) overwrite `VoiceNote.processedTitle` and `VoiceNote.processedBody`; deletion in [UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note) is irreversible. No operator-action audit log exists separately from the operational and security log described in [N2.6](#n26-logging); see [N1 § 15d](N1-nichtfunktional.md) *Audit Requirements* (not applicable).

These are noted explicitly so the absence of the corresponding strategy is a deliberate decision rather than an omission.

---

## N2.10 Cross-references

| Block | Relevance to N2 |
|-------|-----------------|
| [P1](P1-ziele-rahmenbedingungen.md) | [CON-3a-04](P1-constraints.md) (single user) and [NG-01](P1-ziele-rahmenbedingungen.md), [NG-04](P1-ziele-rahmenbedingungen.md) bound the not-applicable concerns in [N2.9](#n29-out-of-scope-for-n2) and the synchronous handling in [N2.5](#n25-failure-handling). |
| [F1](F1-geschaeftsprozesse.md) | The pipeline whose failure semantics [N2.5](#n25-failure-handling) governs. |
| [F2](F2-anwendungsfaelle.md) | The sign-in / recovery flow ([UC-01](F2-anwendungsfaelle.md#uc-01--sign-in)–[UC-04](F2-anwendungsfaelle.md#uc-04--sign-out)) is the visible surface of [N2.4](#n24-authentication-and-session); [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note)–[UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note) is the visible surface of [N2.5](#n25-failure-handling) and [N2.7](#n27-content-sanitisation); [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings) is the visible surface of [N2.2](#n22-type-driven-configuration) and [N2.8](#n28-secret-handling). |
| [F3](F3-anwendungsfunktionen.md) | [AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) is the algorithmic core of [N2.7](#n27-content-sanitisation). |
| [B1](B1-dialogspezifikation.md) | Surfaces validation outcomes from [N2.3](#n23-validation), failure messages from [N2.5](#n25-failure-handling), and the sign-in / recovery dialogues that gate [N2.4](#n24-authentication-and-session). |
| [D1](D1-datenmodell.md) | `VoiceNote.metadata` shape is governed by [N2.2](#n22-type-driven-configuration) / [N2.3](#n23-validation); `errorMessage` carries [N2.5](#n25-failure-handling); `Operator` and `RecoveryToken` underpin [N2.4](#n24-authentication-and-session) and [N2.8](#n28-secret-handling). |
| [D2](D2-datentypen.md) | [D2.4](D2-datentypen.md#d24-messagetypedt) and [D2.7](D2-datentypen.md#d27-typespecificdata) back [N2.2](#n22-type-driven-configuration) / [N2.3](#n23-validation); [D2.5](D2-datentypen.md#d25-notestatusdt) backs [N2.5](#n25-failure-handling); [D2.6](D2-datentypen.md#d26-opaquesecret) backs [N2.8](#n28-secret-handling). |
| [S1](S1-nachbarsysteme.md) | [S1.1](S1-nachbarsysteme.md#s11-conventions) and [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)–[S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api) operationalise [N2.5](#n25-failure-handling) and [N2.8](#n28-secret-handling); [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api) consume the bindings resolved by [N2.2](#n22-type-driven-configuration). |
| [N1](N1-nichtfunktional.md) | NFRs 12a-01, 12d-01, 14a-01, 14a-02, 14c-01, 15a-01, 15a-02, 15a-03, 15a-04, 15b-01, 15b-02, 15b-03, 15b-04 are the binding requirements that the strategies above implement at the boundaries. |
