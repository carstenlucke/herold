# E2 — Glossary

Definition of domain and technical terms used throughout the Herold specification. Listed alphabetically. Cross-references point to other glossary entries or to the relevant specification block.

---

### ADR — Architecture Decision Record

A short document recording one architectural decision: context, options, chosen option, and rationale. Herold's ADRs live in [`docs/arch/`](../arch/) and are referenced from P1 and P2.

### Apache

The HTTP server used by Herold in both development and production. The same server software is used in both environments (CON-3b-01, CON-3b-02; see [ADR-002](../arch/002-dev-prod-parity.md)) to avoid dev/prod drift.

### CRUD

Create, Read, Update, Delete — the four basic operations on persistent data.

### Dispatcher

Herold's role in the system context: it captures voice input and *dispatches* it as a GitHub Issue. Herold does not own the resulting ticket lifecycle.

### Fine-grained PAT

A GitHub Personal Access Token scoped to a single repository and a minimal permission set (`Issues: Read & Write`). Used by Herold to create issues; preferred over classic PATs for least-privilege access.

### GitHub Issue

The external artefact produced by Herold's pipeline. The sole ticket store; Herold pushes one-way and never reads back. See ADR-003.

### Herold

This project. A single-user, voice-to-issue dispatcher built as a Laravel monolith with an Inertia + Vue UI.

### iff

Mathematical shorthand for *if and only if* — a biconditional. "*A* iff *B*" means *A* implies *B* and *B* implies *A*. Used in D2 to state slot-presence conditions (e.g. "a slot is required iff marked required") so the rule is symmetric: the property holds in exactly those cases, not merely in those cases.

### Inertia.js

A protocol that connects a server-side framework (Laravel) with a client-side SPA framework (Vue). Renders Vue components as "pages" but uses Laravel routing and controllers — no separate API layer needed for the browser UI. See ADR-001.

### Issue Content Sanitizer

The application function that renders active markup inert in the dispatched issue body and visually delimits untrusted (operator-derived) content from application-generated structure (NFR-15b-04). Its purpose is to neutralise prompt-injection attempts before they enter the context of any local AI agent that later reads the issue.

### Laravel

The PHP web framework Herold is built on. Version 13.x.

### Laravel AI SDK

The first-party `laravel/ai` package providing a provider-agnostic interface to AI services. Herold uses it for OpenAI Whisper (speech-to- text) and Chat Completion.

### Markdown

Lightweight markup language using plain-text formatting (`# Heading`, `**bold**`, `- list`). Herold's `processed_body` is always Markdown- formatted, matching GitHub Issues' native format.

### Message Type

A category of voice note. The set of categories is fixed by the spec as the `MessageTypeDT` enumeration (see [D2.4](D2-datentypen.md#d24-messagetypedt)): `general`, `youtube`, `diary`, `obsidian`, `todo`. The metadata slot inventory per category is also spec-level (see [D2.7](D2-datentypen.md#d27-typespecificdata)). For each value the host configures the human-readable label, icon, GitHub label, and preprocessing prompt; introducing a new category requires both a spec extension (new enum value, new slot row) and a corresponding host-configuration entry.

### MIME Type

Media type identifier (IETF RFC 6838) used to declare the format of a transferred resource. Herold uses MIME types to gate accepted audio uploads (NFR-15a-03).

### Monolith

A single deployable application that contains all responsibilities (web, domain logic, persistence). Herold is a Laravel monolith — see CON-3a-01 in P1.

### Note Status

The lifecycle state of a voice note. Values: `recorded`, `processed`, `sent`, `error`. The four states are sufficient because processing is synchronous (no intermediate background states). See ADR-002.

### Operator

The single human user of Herold. There is exactly one operator account; multi-user is out of scope.

### Pipeline

The three-step request flow `Record → Process → Send`. Synchronous inside the HTTP request; see P2 and ADR-002.

### Prompt Injection

An attack class in which untrusted input contains instructions intended to influence a downstream LLM (here: a local agent reading the GitHub issue). Mitigated by the Issue Content Sanitizer.

### Recovery

The break-glass flow specified in UC-03. Out-of-band, the operator places a single `RecoveryToken` (D1) in the local file store; through the browser the operator then redeems the token, which atomically rotates `Operator.apiKeyHash` and unbinds the bound TOTP. No privileged shell access is required.

### Shared Hosting

The production environment. Provides PHP 8.5, Apache, HTTPS, FTP, and limited SSH. No Docker, no cron, no long-running processes. See CON-3b-01 in P1.

### SQLite

File-based relational database. No separate server process — the database is a single file on disk. Chosen for Herold's single-user, low- traffic scenario.

### Synchronous Processing

Strategy where Whisper, Chat Completion, and the GitHub push all run inside the HTTP request rather than in a background worker. See ADR-002.

### TOTP

Time-based One-Time Password ([RFC 6238](https://datatracker.ietf.org/doc/html/rfc6238)). A 6-digit code generated by an authenticator app (e.g. Google Authenticator), valid for 30 seconds. Used as second factor in Herold's browser login.

### ULID

Universally Unique Lexicographically Sortable Identifier ([spec](https://github.com/ulid/spec)). A 26-character string (e.g. `01ARZ3NDEKTSV4RRFFQ69G5FAV`) that encodes a millisecond timestamp + randomness. Unlike UUIDs, ULIDs sort chronologically. ULID is the implementation choice that satisfies the time-sortable `Identifier` semantics defined in D2.2 — for the spec, it is sufficient that any chosen identifier scheme provides time-ordered, opaque values.

### Voice Note

The central domain entity (D1.1 `VoiceNote`). A voice note bundles the captured audio, its message type, type-specific extra fields, transcript, generated title and body, status, and — once dispatched — the reference to the issue created at GitHub.

### Vuetify

Vue.js component library implementing Material Design 3. Provides pre- built UI components (buttons, cards, navigation, etc.) with built-in responsive breakpoints.

### Whisper

OpenAI's speech-to-text model. Herold uses it via the Laravel AI SDK to transcribe voice notes into text.
