# D2 — Domain Data Types

> **Status: draft.** Synchronised with the entity attributes catalogued in [D1](D1-datenmodell.md).

D2 catalogues the **non-trivial domain data types** referenced as attribute types in D1. Trivial standard types — `Text`, `Integer`, `Boolean`, `Email`, `URL`, `Timestamp`, `Markdown` — are used at face value and not catalogued here.

Each entry states the *value range*, *equality and ordering semantics*, and *handling rules* a consumer must observe. Implementation choices (column types, encoding, library) are not D2 concerns — they live in `docs/arch/` and in code.

Catalogue entries are listed alphabetically; the section numbering follows that order.

---

## D2.1 Type Catalogue

| Type | Kind | Defined in |
|------|------|-----------|
| `FieldDT` | Enumeration over field types | [§ D2.2](#d22-fielddt) |
| `Identifier` | Opaque, time-sortable key | [§ D2.3](#d23-identifier) |
| `IssueState` | Enumeration | [§ D2.4](#d24-issuestate) |
| `MessageTypeDT` | Enumeration of message types | [§ D2.5](#d25-messagetypedt) |
| `NoteStatusDT` | Enumeration | [§ D2.6](#d26-notestatusdt) |
| `OpaqueSecret` | Opaque value with security semantics | [§ D2.7](#d27-opaquesecret) |
| `TypeSpecificData` | Schema-shaped record | [§ D2.8](#d28-typespecificdata) |

---

## D2.2 FieldDT

*(FieldDT = field data type.)* Enumeration of permitted attribute types for entries in `VoiceNote.extraFields` ([D1.1](D1-datenmodell.md#voicenote)). The host configures, per `MessageTypeDT` value, which named extra-field slots exist and which `FieldDT` each one carries; AF-08 validates the supplied data against that configured shape.

| Value | Permits |
|-------|---------|
| `text` | Free-form `Text`. |
| `date` | Calendar date (no time component). |
| `url` | Absolute `URL`. |
| `integer` | Signed `Integer`. |
| `boolean` | `Boolean`. |

**Equality & ordering.** Equality only.

**Extensibility.** This set is closed; adding a new permitted type is a spec change.

---

## D2.3 Identifier

Used as the primary key type of every entity in [D1](D1-datenmodell.md): `VoiceNote.id`, `Operator.id`, …

**Value form.** Opaque value. Consumers must not parse, compare structurally, or attribute meaning to its internal form.

**Generation.** Created at the moment the owning entity is constructed; never reassigned over the entity's lifetime; never reused after deletion.

**Equality.** Bytewise equality; case-sensitive if rendered.

**Ordering.** Identifiers are *time-sortable*: for two values *a* and *b* generated at distinct moments, the value generated later compares greater. Lexicographic order is therefore a valid creation-time order. Co-creation within the same instant is allowed but the relative order of such values is unspecified.

**Cross-references.** Sortability is the basis for the recency ordering used by UC-09 (*Browse voice notes*).

---

## D2.4 IssueState

Mirrors the lifecycle state of a `GitHubIssue` ([D1.2](D1-datenmodell.md#githubissue)) at GitHub. Read-only from Herold's perspective — Herold does not transition this value (see P1 non-goal [NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle*).

| Value | Meaning |
|-------|---------|
| `open` | Issue is currently open at GitHub. |
| `closed` | Issue has been closed at GitHub. |

**Equality & ordering.** Equality only.

---

## D2.5 MessageTypeDT

*(MessageTypeDT = message type data type.)* Enumeration of the message-type categories a `VoiceNote` ([D1.1](D1-datenmodell.md#voicenote)) can be classified as. Each value is a stable lower-case ASCII slug.

| Value | Meaning |
|-------|---------|
| `general` | Free-form note. |
| `youtube` | Note tied to a YouTube video. |
| `diary` | Diary entry, optionally dated. |
| `obsidian` | Note destined for an Obsidian vault. |
| `todo` | Task or to-do item, optionally with a deadline. |

**Per-value configuration.** Host configuration supplies, for each value, the prompt used by AF-02, the shape of `VoiceNote.extraFields` enforced by AF-08, and the GitHub label written by AF-05. Resolution is performed by AF-04. The configured properties are visible to the operator via UC-12.

**Equality & ordering.** Equality only.

**Extensibility.** This set is closed; introducing a new message type is a spec change (extending the enum) accompanied by a configuration entry on the host.

---

## D2.6 NoteStatusDT

*(NoteStatusDT = note status data type.)* Tracks the position of a `VoiceNote` ([D1.1](D1-datenmodell.md#voicenote)) within the synchronous capture–process–dispatch pipeline.

| Value | Meaning |
|-------|---------|
| `recorded` | Audio captured, awaiting processing. |
| `processed` | Structured content generated, awaiting review and dispatch. |
| `sent` | Dispatched to GitHub; `VoiceNote.githubIssueNumber` and `VoiceNote.githubIssueUrl` populated. |
| `error` | The last attempted transition failed; `VoiceNote.errorMessage` is populated. |

**Equality & ordering.** Equality only; values are not ordered.

**Reachable transitions.** Defined in F3.AF-06. The set above is closed; new values require a spec change.

---

## D2.7 OpaqueSecret

A type tag for credential or secret material (`Operator.apiKeyHash`, `Operator.totpSecret`, `RecoveryToken.token`, and the host-configured GitHub access token).

**Handling rules.**

- Values are never rendered to a UI surface. UC-12 *View settings* shows the *presence* of an `OpaqueSecret`, never the value.
- Values are never returned in an outbound API payload, log line, error message, or diagnostic.
- Comparison is constant-time when used for verification.
- A value, once stored, is replaceable but never round-tripped to the operator. Recovery flows replace, not reveal.

**Equality.** Equality is verification-only and constant-time. Direct equality between two `OpaqueSecret` values outside a verification context is not defined.

**Cross-references.** [NFR-15a-02](N1-nichtfunktional.md), [E2](E2-glossar.md) (*fine-grained PAT*).

---

## D2.8 TypeSpecificData

A record whose **shape is declared by host configuration for the bound `MessageTypeDT`** rather than fixed at the D1 level. Used as the type of `VoiceNote.extraFields`.

**Shape rules.**

- Each named slot declared for the bound `MessageTypeDT` carries one value of a declared `FieldDT`.
- A slot is required iff its declaration marks it as required; optional slots may be absent.
- No slots beyond those declared for the bound `MessageTypeDT` are permitted.

**Validation.** Performed by AF-08 on capture (UC-05) and on edit (UC-07) against the shape configured for `VoiceNote.type`.

**Equality.** Two values are equal iff their declared slots have equal values under the equality of their respective `FieldDT`s.

---

## D2.9 Notation Conventions

The following multiplicity and composition notations are used in D1 and D2 attribute tables:

| Notation | Meaning |
|----------|---------|
| `T` | Exactly one value of type `T`. |
| `T [0..1]` | Zero or one. |
| `T [n..m]` | Between *n* and *m* values. |
| `Set<T>` | Unordered collection of `T`, no duplicates. |
| `List<T>` | Ordered collection of `T`. |

---

## D2.10 Cross-references

| Block | Relevance to D2 |
|-------|-----------------|
| [D1](D1-datenmodell.md) | Every type in this catalogue appears as an attribute type in at least one D1 entity. |
| [F3](F3-anwendungsfunktionen.md) | AF-06 transitions `NoteStatusDT`; AF-04 resolves the configuration bound to a `MessageTypeDT` value; AF-08 validates `TypeSpecificData` against the configured shape (named slots and their `FieldDT`s) for the bound `MessageTypeDT`. |
| [N1](N1-nichtfunktional.md) | Handling rules for `OpaqueSecret` are reinforced by content-sanitisation and rate-limiting NFRs. |
| [E2](E2-glossar.md) | Glossary entries for *fine-grained PAT*, *message type*. |
