# N2 — Cross-cutting Concepts

> **Status: stub.** This block records the cross-cutting concepts referenced from other blocks. Each concept is sketched here at the level needed to make those references meaningful; full elaboration is deferred until a concrete concept changes shape.

Cross-cutting concepts in the sense of Siedersleben (chapter 4.7): unified solutions for recurring problems that cut across individual functions, use cases, and data types. N2 captures the *strategy* a recurring concern follows; details that are specific to a single function or entity remain at their primary site.

The current building plan calls out the following concepts. They are deliberately listed without internal numbering until the block is fully drafted.

---

## Type-driven configuration

**Concern.** Herold supports a closed set of message types ([D2.4](D2-datentypen.md#d24-messagetypedt) *MessageTypeDT*). Several behaviours are bound per type — the slot inventory for extra fields, the generation prompt, the GitHub label. These bindings must be obtained through a single resolution point so that adding or changing a type touches exactly one configuration surface, and so that no consumer hard-codes message-type knowledge.

**Strategy.**

- The catalogue of valid identifiers is closed and lives in [D2.4](D2-datentypen.md#d24-messagetypedt). The slot inventory per identifier is fixed at spec level in [D2.7](D2-datentypen.md#d27-typespecificdata).
- The prompt and label per identifier are supplied by the host configuration out-of-band; they are not part of the application's persisted data ([D1](D1-datenmodell.md) does not model them).
- Every consumer ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api), [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings)) obtains its binding through this single resolution point. Unknown identifiers are rejected at the boundary that observes them.

**Cross-references.** [D2.4](D2-datentypen.md#d24-messagetypedt), [D2.7](D2-datentypen.md#d27-typespecificdata), [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api).

---

## Validation

**Concern.** Operator-supplied input enters the system at two points: capture submission ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note)) and edit save ([UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content)). Both must apply the same rules to the same data shape ([D2.7](D2-datentypen.md#d27-typespecificdata) *TypeSpecificData*).

**Strategy.**

- Validation is *strict*: missing required slots, type-incorrect values, and unknown slot names all fail.
- Validation is *schema-driven*: the schema is the slot inventory declared for the bound `MessageTypeDT` in [D2.7](D2-datentypen.md#d27-typespecificdata).
- Validation is performed at the boundary that accepts the input. Nothing is persisted before the input is accepted; on rejection the offending fields are surfaced to the operator.
- The audio recording itself is not validated against this schema; its format and size constraints are governed by [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation* and apply at [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api).

**Cross-references.** [D2.7](D2-datentypen.md#d27-typespecificdata), [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content).

---

## Authentication and 2FA

Treated as a cross-cutting concern enforced by the surrounding session. Underpins [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) through [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out). Detailed strategy is deferred; see [NFR-15a-01](N1-nichtfunktional.md), [NFR-15a-02](N1-nichtfunktional.md), [NFR-15a-04](N1-nichtfunktional.md) and the `Operator` / `RecoveryToken` entities in [D1.1](D1-datenmodell.md#operator).

---

## N2 Cross-references

| Block | Relevance to N2 |
|-------|-----------------|
| [D2](D2-datentypen.md) | *Type-driven configuration* and *Validation* refer back to [D2.4](D2-datentypen.md#d24-messagetypedt) and [D2.7](D2-datentypen.md#d27-typespecificdata) for the catalogue and the schema. |
| [S1](S1-nachbarsysteme.md) | The resolved prompt and label drive [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api) and [S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api). |
| [F2](F2-anwendungsfaelle.md) | Validation runs at [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) and [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content); the resolved bindings are surfaced via [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings). |
| [N1](N1-nichtfunktional.md) | [NFR-15a-01](N1-nichtfunktional.md), [NFR-15a-02](N1-nichtfunktional.md), [NFR-15a-04](N1-nichtfunktional.md) constrain the authentication concept. |
