# E2 — Glossary

Definition of domain and technical terms used throughout the Herold specification. Listed alphabetically. Cross-references point to other glossary entries or to the relevant specification block.

---

### ADR — Architecture Decision Record

A short document recording one architectural decision: context, options, chosen option, and rationale. Herold's ADRs live in [`docs/arch/`](../arch/) and are referenced from P1 and P2.

### CRUD

Create, Read, Update, Delete — the four basic operations on persistent data.

### Dispatcher

Herold's role in the system context: it captures voice input and *dispatches* it as a GitHub Issue. Herold does not own the resulting ticket lifecycle.

### Fine-grained PAT

A GitHub Personal Access Token scoped to a single repository and a minimal permission set (`Issues: Read & Write`). Used by Herold to create issues; preferred over classic PATs for least-privilege access.

### GitHub Issue

The external artefact produced by Herold's pipeline. The sole ticket store; Herold pushes one-way and never reads back. See ADR-003.

### Herold

This project. A single-user, voice-to-issue dispatcher with a thin browser UI; the concrete platform is an architecture concern.

### iff

Mathematical shorthand for *if and only if* — a biconditional. "*A* iff *B*" means *A* implies *B* and *B* implies *A*. Used in D2 to state slot-presence conditions (e.g. "a slot is required iff marked required") so the rule is symmetric: the property holds in exactly those cases, not merely in those cases.

### Issue Content Sanitizer

The application function that renders active markup inert in the dispatched issue body and visually delimits untrusted (operator-derived) content from application-generated structure (NFR-15b-04). Its purpose is to neutralise prompt-injection attempts before they enter the context of any local AI agent that later reads the issue.

### Markdown

Lightweight markup language using plain-text formatting (`# Heading`, `**bold**`, `- list`). Herold's `processed_body` is always Markdown- formatted, matching GitHub Issues' native format.

### Message Type

A category of voice note. The set of categories is fixed by the spec as the `MessageTypeDT` enumeration (see [D2.4](D2-datentypen.md#d24-messagetypedt)): `general`, `youtube`, `diary`, `obsidian`, `todo`. The metadata slot inventory per category is also spec-level (see [D2.7](D2-datentypen.md#d27-typespecificdata)). For each value the host configures the human-readable label, icon, GitHub label, and preprocessing prompt; introducing a new category requires both a spec extension (new enum value, new slot row) and a corresponding host-configuration entry.

### MIME Type

Media type identifier (IETF RFC 6838) used to declare the format of a transferred resource. Herold uses MIME types to gate accepted audio uploads (NFR-15a-03).

### Monolith

A single deployable application that contains all responsibilities (web, domain logic, persistence). Herold is structured as a monolith; the concrete platform is an architecture concern.

### Note Status

The lifecycle state of a voice note. Values: `recorded`, `processed`, `sent`. The three states are sufficient because processing is synchronous (no intermediate background states); failures are captured in the orthogonal `errorMessage` flag rather than as a separate state (see [D2.5](D2-datentypen.md#d25-notestatusdt) and ADR-002).

### Operator

The single human user of Herold. There is exactly one operator account; multi-user is out of scope.

### Out-of-band

Outside the application's regular request/response path. An out-of-band action uses a separate channel — the host file system, FTP, SSH, environment variables, or hand-edited configuration files — rather than the browser UI or an HTTP endpoint. The opposite is *in-band*: through the application itself. The term originates in telecommunications, where in-band signalling shares the channel with payload data and out-of-band signalling uses a separate channel. Herold uses out-of-band channels for host configuration (per-type prompt, label, icon, GitHub label), credential placement (`.env`), and the recovery flow (UC-03).

### Pipeline

The three-step request flow `Record → Process → Send`. Synchronous inside the HTTP request; see P2 and ADR-002.

### Prompt Injection

An attack class in which untrusted input contains instructions intended to influence a downstream LLM (here: a local agent reading the GitHub issue). Mitigated by the Issue Content Sanitizer.

### Recovery

The break-glass flow specified in UC-03. Out-of-band, the operator places a single `RecoveryToken` (D1) in the local file store; through the browser the operator then redeems the token, which atomically rotates `Operator.apiKeyHash` and unbinds the bound TOTP. No privileged shell access is required.

### Shared Hosting

The production environment. Provides HTTPS at the public web edge, an out-of-band write channel into the host file store, and optional limited shell access for one-off maintenance. No container runtime, no scheduler, no long-running processes. See [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production).

### Synchronous Processing

Strategy where Whisper, Chat Completion, and the GitHub push all run inside the HTTP request rather than in a background worker. See ADR-002.

### TOTP

Time-based One-Time Password ([RFC 6238](https://datatracker.ietf.org/doc/html/rfc6238)). A 6-digit code generated by an authenticator app (e.g. Google Authenticator), valid for 30 seconds. Used as second factor in Herold's browser login.

### Voice Note

The central domain entity (D1.1 `VoiceNote`). A voice note bundles the captured audio, its message type, type-specific extra fields, transcript, generated title and body, status, and — once dispatched — the reference to the issue created at GitHub.

### Whisper

OpenAI's speech-to-text model. Herold uses it to transcribe voice notes into text; the concrete client is an architecture concern.
