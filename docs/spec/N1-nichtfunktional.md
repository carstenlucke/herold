# N1 — Non-Functional Requirements

Measurable quality requirements with fit criteria. Distinct from project constraints (which define the solution space) — those live in [`P1-constraints.md`](P1-constraints.md).

Based on the [Volere Requirements Specification Template](https://www.volere.org/templates/volere-requirements-specification-template/), Sections 10–17 (Robertson & Robertson). Only sections relevant to Herold are included. Empty Volere sections are omitted.

---

## 10. Look and Feel Requirements

### 10a. Appearance Requirements

**NFR-10a-01: Responsive Design**

The UI must be fully usable on both smartphone and desktop. Both contexts are equally important: voice recording on the go on a smartphone, review and editing on a desktop. The layout must adapt to viewport width without horizontal scrolling on any supported viewport.

- Touch-optimized on touch devices: minimum tap target size of 48x48dp.
- All views must be fully usable on viewports from 320px width upwards.

**Fit Criterion:** Every view renders correctly and is fully operable on a smartphone-class viewport (e.g. iPhone SE, 375x667) and a desktop-class viewport (e.g. 1920x1080).

---

## 11. Usability and Humanity Requirements

### 11a. Ease of Use Requirements

**NFR-11a-01: Voice Recording in Under 3 Taps**

Starting a voice recording must require no more than 3 taps from any screen in the application.

**Fit Criterion:** From Dashboard, user reaches active recording in <= 3 taps.

### 11b. Personalization and Internationalization Requirements

*Not applicable. Single-user, single-language (English UI).*

### 11c. Learning Requirements

**NFR-11c-01: No Training Required**

The application must be usable without documentation or training. Type selection, recording, and ticket creation must be self-explanatory.

**Fit Criterion:** A technically literate user can record, process, and submit a ticket on first use without consulting documentation.

### 11d. Accessibility Requirements

*Deferred. Not a priority for a single-user personal tool.*

---

## 12. Performance Requirements

### 12a. Speed and Latency Requirements

**NFR-12a-01: Synchronous Processing**

Audio transcription, LLM preprocessing, and GitHub issue creation run synchronously in the HTTP request. No queue, no cron, no background jobs. See [ADR-002](../arch/002-dev-prod-parity.md).

- Processing time: typically up to ~5 seconds for short notes and ~10–15 seconds for notes around one to two minutes. Longer notes are not the design target — Herold dispatches voice notes capturing task intent, not meeting or lecture transcripts (see NG-09 in [P1.4](P1-ziele-rahmenbedingungen.md#out-of-scope)).
- The UI shows a loading indicator during processing
- Errors surface immediately in the HTTP response

**Fit Criterion:** After clicking "Process", the response returns the processed result (or an error) within 30 seconds for a note of up to ~2 minutes of audio. A loading indicator is visible throughout.

### 12b. Safety-Critical Requirements

*Not applicable.*

### 12c. Precision Requirements

*Not applicable.*

### 12d. Reliability and Availability Requirements

**NFR-12d-01: Synchronous Error Handling**

If an API call (OpenAI, GitHub) fails during synchronous processing, the voice note's status does **not** advance (see [D2.5](D2-datentypen.md#d25-notestatusdt) *NoteStatusDT*); `VoiceNote.errorMessage` is populated with the failure reason. The user can manually retry from the UI via the "Process" or "Send" button; on a successful retry `errorMessage` is cleared and the documented status transition fires.

No automatic retries. Each retry is an explicit user action.

**Fit Criterion:** Transient API failures (timeouts, 5xx) do not result in data loss. The voice note and audio file are preserved. The error message is displayed to the user.

### 12e. Capacity Requirements

*Not critical. Single-user system. Expected data volume is below 10,000 voice notes over the application's lifetime.*

---

## 13. Operational and Environmental Requirements

### 13a. Expected Physical Environment

**NFR-13a-01: Mobile and Desktop Usage**

The application is used both in mobile environments (smartphone, voice recording on the go) and at a desk (desktop browser, review and editing). It must function on current major browsers in both contexts, including the in-browser audio capture used by UC-05.

**Fit Criterion:** Voice recording, playback, review, and editing work on current major mobile browsers (Safari iOS, Chrome Android) and current major desktop browsers (Safari, Chrome, Firefox) on devices the operator is reasonably expected to use.

### 13b. Expected Technological Environment

**NFR-13b-01: Shared Hosting Compatibility**

The application must run on standard shared hosting that provides only:

- The runtime mandated by P1-constraints (see CON-3a-01 and CON-3a-03).
- HTTPS provided by the hosting provider (assumption AS-03).
- Out-of-band file-store write access (e.g. via FTP) for deployment and for the recovery channel (UC-03).
- Optional limited shell access for one-off maintenance only — no scheduled jobs, no long-running workers.

The application must not depend on container runtimes, cron-scheduled tasks, or background workers in production (CON-3b-01, [ADR-002](../arch/002-dev-prod-parity.md)).

**Fit Criterion:** The application can be deployed by uploading the build artefacts to the hosting account's web root via FTP-style transfer and operates correctly without scheduled jobs or background workers.

### 13c. Partner or Collaborative Applications

**NFR-13c-01: Agent Interoperability**

Local AI agents (Claude Code, OpenCode) interact exclusively with the GitHub Issues neighbour ([S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api)), not with Herold. See [ADR-003](../arch/003-github-issues-as-ticket-store.md).

- Ticket consumption: read dispatched issues and their content.
- Status updates: comment on or relabel issues to reflect work progress.
- Agent memory: managed locally by each agent, outside Herold.

**Fit Criterion:** An agent can read tickets and update their status through the GitHub Issues neighbour interface alone. Herold exposes no API to agents and none is required.

### 13d. Productization Requirements

*Not applicable. Personal tool, no distribution planned.*

---

## 14. Maintainability and Support Requirements

### 14a. Maintenance Requirements

**NFR-14a-01: Layered Message-Type Definition**

The definition of a message type is split between the spec/codebase and host-level configuration, mirroring the strategy in [N2](N2-querschnittskonzepte.md) *Type-driven configuration*:

- **Spec/code layer** — the closed catalogue of `MessageTypeDT` identifiers ([D2.4](D2-datentypen.md#d24-messagetypedt)) and the per-type slot inventory of `TypeSpecificData` ([D2.7](D2-datentypen.md#d27-typespecificdata)). Adding a new type or changing its slot inventory is a spec change, accompanied by the corresponding code change.
- **Host-config layer** — per-type display label, icon, GitHub label, and preprocessing prompt. These bindings are supplied out-of-band and are not part of the application's persisted data ([D1](D1-datenmodell.md)).

Once the spec/code layer declares a type, all per-type bindings in the host-config layer must be adjustable without code changes.

**Fit Criterion:** For a `MessageTypeDT` value already declared at spec level, its display label, icon, GitHub label, and preprocessing prompt can each be changed by editing host configuration only, without editing application code.

**NFR-14a-02: Out-of-Band Auth Recovery**

Authentication (API key + TOTP) must be resettable without privileged shell access. The recovery channel relies on out-of-band write access to the host file store (e.g. via FTP) plus the browser; see UC-03 for the redemption flow.

**Fit Criterion:** A locked-out operator can regain access using only out-of-band file-store access and a browser; no CLI or shell commands are required on the host.

### 14b. Supportability Requirements

*Not applicable. No external support team.*

### 14c. Adaptability Requirements

**NFR-14c-01: AI Provider Portability**

Switching the AI provider (e.g. from OpenAI to Anthropic or Gemini) must be a local change. Provider-specific code is confined to a single integration point per neighbour ([S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api) transcription, [S1.4](S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api) content generation); the rest of the application — domain logic, persistence, UI, pipeline orchestration — must remain unchanged. Provider selection and credentials are supplied out-of-band via host configuration.

**Fit Criterion:** Replacing the active AI provider requires editing only the single integration point per neighbour plus host configuration. No change is required outside those isolation points for transcription and content generation to continue functioning.

---

## 15. Security Requirements

### 15a. Access Requirements

**NFR-15a-01: Two-Factor Browser Authentication**

Browser access requires API key + TOTP (Time-based One-Time Password). Two factors: something you know (API key) + something you have (Authenticator app).

**Fit Criterion:** Access is denied if either factor is missing or incorrect.

**NFR-15a-02: Login Rate Limiting and Lockout**

Both factors of the sign-in flow (UC-01) and the recovery channel (UC-03) must enforce rate limiting per source IP.

- Sign-in: max 5 attempts per minute per IP.
- Sign-in lockout: 15-minute block after 10 failed attempts per IP.
- Recovery: max 5 attempts per hour per IP.

**Fit Criterion:** After 5 failed sign-in attempts within one minute, the next attempt is rejected. After 10 failed attempts, all sign-in attempts from that IP are blocked for 15 minutes. Recovery attempts beyond 5 per hour from the same IP are rejected.

**NFR-15a-03: Audio Upload Validation**

Audio uploads (UC-05) must be validated server-side:
- Maximum file size: 25 MB.
- Accepted formats: the common audio container/codec combinations produced by browser audio capture.
- Rate limit: max 20 uploads per hour.

**Fit Criterion:** An upload exceeding 25 MB or in an unaccepted format is rejected before the note is persisted. The 21st upload within one hour from the same operator is rejected.

**NFR-15a-04: Recovery Token Expiry**

The `RecoveryToken` (D1) must have a time-to-live of 60 minutes counted from `RecoveryToken.placedAt`. After the time-to-live expires, the token must not grant recovery access. A missing token, a token with a non-matching secret, and an expired token must surface to the operator as the same generic rejection — the internal reason for rejection must not be disclosed externally, but must be recorded in the application log together with the source IP and the time of the attempt.

**Fit Criterion:** A `RecoveryToken` older than 60 minutes does not grant access to UC-03. The three failure modes (missing, mismatched, expired) are externally indistinguishable. Each rejection produces a log entry containing source IP and timestamp.

### 15b. Integrity Requirements

**NFR-15b-01: No API Keys in Frontend**

All external API credentials (OpenAI, GitHub PAT) are held server-side only. The frontend never receives or transmits these credentials.

**Fit Criterion:** No external API credential is observable in any request or response received by the browser.

**NFR-15b-02: No Preprocessing Prompts Surfaced to the Browser**

The browser-facing view of the configuration (UC-12) must surface only those per-`MessageTypeDT` properties the operator needs to choose a type and fill its extra fields — i.e. the human-readable label, icon, GitHub label, and the extra-field schema. The configured preprocessing prompt for any `MessageTypeDT` value is server-only and must never be transmitted to the browser, neither in UC-12 nor anywhere else.

**Fit Criterion:** No payload reaching the browser contains the contents of any configured preprocessing prompt.

**NFR-15b-03: Secret Redaction in Logs**

Sensitive values must not appear in the application log. A redaction mechanism must mask the application's framework key, the operator's API key, third-party API tokens (OpenAI, GitHub), and any session or bearer token. Transcript contents (`VoiceNote.transcript`) must not be logged — only processing events that reference the note by `VoiceNote.id` are permitted.

**Fit Criterion:** Inspection of the application log reveals no API key, token, or transcript text.

**NFR-15b-04: Issue Content Sanitization**

Voice note content (transcript, generated title, generated body) is untrusted input. Before the dispatched issue (UC-08) is composed, the application must:

- Render any active markup (e.g. embedded HTML, script-bearing URIs) inert in the issue body.
- Visually delimit operator-derived content from application-generated structure inside the issue body, so a downstream reader can tell which is which.
- Exclude any prompt-engineering material (e.g. configured preprocessing prompts) from the issue body.

**Fit Criterion:** An attempted injection in a transcript (e.g. an embedded markup comment instructing a downstream agent to ignore previous instructions) appears as inert text in the dispatched issue. The issue body's structure makes the boundary between operator-derived content and application-generated structure self-evident.

### 15c. Privacy Requirements

*Voice recordings and transcripts are stored on the server. Single-user system, no third-party data processing beyond OpenAI API (which receives audio for transcription). No GDPR implications as it is a personal tool.*

### 15d. Audit Requirements

*Not applicable for a personal tool.*

---

## 16. Cultural Requirements

### 16a. Cultural Requirements

**NFR-16a-01: Language Convention**

- UI text (labels, buttons, messages): English
- Code, comments, variable names: English
- Documentation (README, ADRs, docs/): English
- Git commits: English (conventional commits)
- Exception: the original German project specification source remains in German.

**Fit Criterion:** No German text appears in the UI, codebase, or documentation, except in the original German project specification source.

---

## 17. Compliance Requirements

### 17a. Legal Requirements

*Not applicable. Personal tool, no distribution, no user data from third parties.*

### 17b. Standards Compliance

**NFR-17b-01: Conventional Commits**

Git commit messages follow the Conventional Commits specification.

**Fit Criterion:** All commits match the pattern `type(scope): description`.
