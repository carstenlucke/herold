# Non-Functional Requirements

Based on the Volere Requirements Specification Template, Sections 10-17 (Robertson & Robertson).
Only sections relevant to Herold are included. Empty Volere sections are omitted.

For project constraints (Volere Section 5), see [`constraints.md`](./constraints.md).

---

## 10. Look and Feel Requirements

### 10a. Appearance Requirements

**NFR-10a-01: Mobile First Design**

The UI must be designed mobile first. The primary usage context is a smartphone
(voice recording on the go), desktop is secondary.

- Vuetify 4 breakpoints (xs/sm/md/lg/xl) must be used consistently
- Touch-optimized: large tap targets (min 48x48dp), swipe gestures where appropriate
- Bottom navigation on mobile, side navigation on desktop
- All views must be fully usable on screens >= 320px width

**Fit Criterion:** Every view renders correctly and is fully operable on
iPhone SE (375x667) and desktop (1920x1080).

---

## 11. Usability and Humanity Requirements

### 11a. Ease of Use Requirements

**NFR-11a-01: Voice Recording in Under 3 Taps**

Starting a voice recording must require no more than 3 taps from any screen
in the application.

**Fit Criterion:** From Dashboard, user reaches active recording in <= 3 taps.

### 11b. Personalization and Internationalization Requirements

*Not applicable. Single-user, single-language (English UI).*

### 11c. Learning Requirements

**NFR-11c-01: No Training Required**

The application must be usable without documentation or training.
Type selection, recording, and ticket creation must be self-explanatory.

**Fit Criterion:** A technically literate user can record, process, and
submit a ticket on first use without consulting documentation.

### 11d. Accessibility Requirements

*Deferred. Not a priority for a single-user personal tool, but Vuetify 4's
built-in a11y support should be preserved (ARIA labels, keyboard navigation).*

---

## 12. Performance Requirements

### 12a. Speed and Latency Requirements

**NFR-12a-01: Asynchronous Processing**

Audio transcription, LLM preprocessing, and GitHub issue creation must not
block the UI. These operations run as queued jobs.

- Local: Docker queue worker processes jobs immediately
- Production: Cron-based scheduler processes jobs within 1 minute

**Fit Criterion:** After clicking "Process", the UI returns to an interactive
state within 1 second. Processing status is shown via polling.

### 12b. Safety-Critical Requirements

*Not applicable.*

### 12c. Precision Requirements

*Not applicable.*

### 12d. Reliability and Availability Requirements

**NFR-12d-01: Failed Jobs Are Retryable**

If an API call (OpenAI, GitHub) fails, the job must be retried up to 3 times.
After 3 failures, the voice note is set to ERROR status with an error message.
The user can manually retry from the UI.

**Fit Criterion:** Transient API failures (timeouts, 5xx) do not result in
data loss. The voice note and audio file are preserved.

### 12e. Capacity Requirements

*Not critical. Single-user system. SQLite handles the expected data volume
(< 10,000 voice notes over the application's lifetime).*

---

## 13. Operational and Environmental Requirements

### 13a. Expected Physical Environment

**NFR-13a-01: Mobile Usage (On the Go)**

The primary usage context is a smartphone in a mobile environment.
The app must function on modern mobile browsers (Safari iOS, Chrome Android).
MediaRecorder API support is required.

**Fit Criterion:** Voice recording and playback work on Safari >= 17 and
Chrome >= 120 on mobile devices.

### 13b. Expected Technological Environment

**NFR-13b-01: Shared Hosting Compatibility**

The application must run on standard shared hosting with:
- PHP >= 8.5 with pdo_sqlite extension
- Cron job support (minimum: 1-minute interval)
- FTP access for deployment
- HTTPS (provided by hosting)
- No shell/SSH access
- No Docker support

**Fit Criterion:** Application deploys and runs correctly on the target
shared hosting environment via FTP upload.

### 13c. Partner or Collaborative Applications

**NFR-13c-01: Agent Interoperability**

Local AI agents (Claude Code, OpenCode) must be able to interact with the
system via:
- GitHub Issues API (via `gh` CLI) for ticket consumption
- Herold Agent API (via `curl`) for memory and ticket status

**Fit Criterion:** An agent can read tickets, update status, and read/write
memories using only `gh` and `curl` commands.

### 13d. Productization Requirements

*Not applicable. Personal tool, no distribution planned.*

---

## 14. Maintainability and Support Requirements

### 14a. Maintenance Requirements

**NFR-14a-01: Config-Driven Message Types**

New message types (note/ticket types) must be addable via configuration only
(`config/herold.php`). No code changes required unless a new type needs a
new external API integration.

**Fit Criterion:** A new type with label, icon, GitHub label, extra fields,
and preprocessing prompt can be added by editing one config file.

**NFR-14a-02: Auth Recovery Without Shell Access**

Authentication (API key + TOTP) must be resettable without shell access.
Recovery mechanism: upload `.herold-recovery` file via FTP, then visit
`/recovery` route in browser.

**Fit Criterion:** A locked-out user can regain access using only FTP and
a browser, without any CLI commands.

### 14b. Supportability Requirements

*Not applicable. No external support team.*

### 14c. Adaptability Requirements

**NFR-14c-01: AI Provider Portability**

The AI service layer (`laravel/ai`) must allow switching the AI provider
(e.g., from OpenAI to Anthropic or Gemini) via configuration, without
changing application code.

**Fit Criterion:** Changing `AI_PROVIDER` in `.env` switches the transcription
and chat provider.

---

## 15. Security Requirements

### 15a. Access Requirements

**NFR-15a-01: Two-Factor Browser Authentication**

Browser access requires API key + TOTP (Time-based One-Time Password).
Two factors: something you know (API key) + something you have (Authenticator app).

**Fit Criterion:** Access is denied if either factor is missing or incorrect.

**NFR-15a-02: Scoped Agent Tokens**

Agent access uses Laravel Sanctum bearer tokens with granular scopes:
- `memory:read`, `memory:write`
- `tickets:read`, `tickets:status`

Tokens are created, listed, and revoked via the Settings UI.

**Fit Criterion:** An agent token with only `memory:read` scope cannot
write memories or access tickets.

### 15b. Integrity Requirements

**NFR-15b-01: No API Keys in Frontend**

All external API keys (OpenAI, GitHub PAT) are stored server-side only.
The frontend never receives or transmits these keys.

**Fit Criterion:** Browser DevTools network tab shows no external API keys
in any request or response.

### 15c. Privacy Requirements

*Voice recordings and transcripts are stored on the server. Single-user
system, no third-party data processing beyond OpenAI API (which receives
audio for transcription). No GDPR implications as it is a personal tool.*

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
- Exception: `spec/herold.md` remains in German

**Fit Criterion:** No German text appears in the UI, codebase, or
documentation (except `spec/herold.md`).

---

## 17. Compliance Requirements

### 17a. Legal Requirements

*Not applicable. Personal tool, no distribution, no user data from third parties.*

### 17b. Standards Compliance

**NFR-17b-01: Conventional Commits**

Git commit messages follow the Conventional Commits specification.

**Fit Criterion:** All commits match the pattern `type(scope): description`.
