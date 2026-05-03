# N1 — Non-Functional Requirements

Measurable quality requirements with fit criteria. Distinct from project constraints (which define the solution space) — those live in [`P1-constraints.md`](P1-constraints.md).

Based on the [Volere Requirements Specification Template](https://www.volere.org/templates/volere-requirements-specification-template/), Sections 10–17 (Robertson & Robertson). Only sections relevant to Herold are included. Empty Volere sections are omitted.

---

## 10. Look and Feel Requirements

### 10a. Appearance Requirements

**NFR-10a-01: Mobile First Design**

The UI must be designed mobile first. The primary usage context is a smartphone (voice recording on the go), desktop is secondary.

- Vuetify 4 breakpoints (xs/sm/md/lg/xl) must be used consistently
- Touch-optimized: large tap targets (min 48x48dp), swipe gestures where appropriate
- Bottom navigation on mobile, side navigation on desktop
- All views must be fully usable on screens >= 320px width

**Fit Criterion:** Every view renders correctly and is fully operable on iPhone SE (375x667) and desktop (1920x1080).

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

*Deferred. Not a priority for a single-user personal tool, but Vuetify 4's built-in a11y support should be preserved (ARIA labels, keyboard navigation).*

---

## 12. Performance Requirements

### 12a. Speed and Latency Requirements

**NFR-12a-01: Synchronous Processing**

Audio transcription, LLM preprocessing, and GitHub issue creation run synchronously in the HTTP request. No queue, no cron, no background jobs. See [ADR-002](../arch/002-dev-prod-parity.md).

- Processing time: ~10-30 seconds per voice note
- The UI shows a loading indicator during processing
- Errors surface immediately in the HTTP response

**Fit Criterion:** After clicking "Process", the response returns the processed result (or an error) within 60 seconds. A loading indicator is visible throughout.

### 12b. Safety-Critical Requirements

*Not applicable.*

### 12c. Precision Requirements

*Not applicable.*

### 12d. Reliability and Availability Requirements

**NFR-12d-01: Synchronous Error Handling**

If an API call (OpenAI, GitHub) fails during synchronous processing, the voice note is set to ERROR status with the error message. The user can manually retry from the UI via the "Process" or "Send" button.

No automatic retries. Each retry is an explicit user action.

**Fit Criterion:** Transient API failures (timeouts, 5xx) do not result in data loss. The voice note and audio file are preserved. The error message is displayed to the user.

### 12e. Capacity Requirements

*Not critical. Single-user system. SQLite handles the expected data volume (< 10,000 voice notes over the application's lifetime).*

---

## 13. Operational and Environmental Requirements

### 13a. Expected Physical Environment

**NFR-13a-01: Mobile Usage (On the Go)**

The primary usage context is a smartphone in a mobile environment. The app must function on modern mobile browsers (Safari iOS, Chrome Android). MediaRecorder API support is required.

**Fit Criterion:** Voice recording and playback work on Safari >= 17 and Chrome >= 120 on mobile devices.

### 13b. Expected Technological Environment

**NFR-13b-01: Shared Hosting Compatibility**

The application must run on standard shared hosting with:
- PHP >= 8.5 with pdo_sqlite extension
- FTP access for deployment
- HTTPS (provided by hosting)
- Limited SSH access (PHP 8.5 available, `crontab` not available)
- No cron jobs required (synchronous processing, see [ADR-002](../arch/002-dev-prod-parity.md))
- No Docker support

**Fit Criterion:** Application deploys and runs correctly on the target shared hosting environment via FTP upload plus optional one-off SSH maintenance commands (for example `php artisan migrate --force`).

### 13c. Partner or Collaborative Applications

**NFR-13c-01: Agent Interoperability**

Local AI agents (Claude Code, OpenCode) interact exclusively with GitHub Issues, not with Herold. See [ADR-003](../arch/003-github-issues-as-ticket-store.md).

- Ticket consumption: `gh issue list`, `gh issue view`
- Status updates: `gh issue comment`, `gh issue edit` (labels)
- Agent memory: file-based, managed locally by each agent (e.g., `CLAUDE.md`)

**Fit Criterion:** An agent can read tickets and update their status using only `gh` CLI commands. No Herold API access required.

### 13d. Productization Requirements

*Not applicable. Personal tool, no distribution planned.*

---

## 14. Maintainability and Support Requirements

### 14a. Maintenance Requirements

**NFR-14a-01: Config-Driven Message Types**

New message types (note/ticket types) must be addable via configuration only (`config/herold.php`). No code changes required unless a new type needs a new external API integration.

**Fit Criterion:** A new type with label, icon, GitHub label, extra fields, and preprocessing prompt can be added by editing one config file.

**NFR-14a-02: Auth Recovery via FTP**

Authentication (API key + TOTP) must be resettable without CLI commands. Recovery mechanism: upload `.herold-recovery` file via FTP, then visit `/recovery` route in browser.

**Fit Criterion:** A locked-out user can regain access using only FTP and a browser.

### 14b. Supportability Requirements

*Not applicable. No external support team.*

### 14c. Adaptability Requirements

**NFR-14c-01: AI Provider Portability**

The AI service layer (`laravel/ai`) must allow switching the AI provider (e.g., from OpenAI to Anthropic or Gemini) via configuration, without changing application code.

**Fit Criterion:** Changing `AI_PROVIDER` in `.env` switches the transcription and chat provider.

---

## 15. Security Requirements

### 15a. Access Requirements

**NFR-15a-01: Two-Factor Browser Authentication**

Browser access requires API key + TOTP (Time-based One-Time Password). Two factors: something you know (API key) + something you have (Authenticator app).

**Fit Criterion:** Access is denied if either factor is missing or incorrect.

**NFR-15a-02: Login Rate Limiting and Lockout**

Login routes (`/login/key`, `/login/totp`) must enforce rate limiting. Recovery route (`/recovery`) must enforce rate limiting.

- Login: max 5 attempts per minute per IP
- Login lockout: 15-minute block after 10 failed attempts per IP
- Recovery: max 5 attempts per hour per IP

**Fit Criterion:** After 5 failed login attempts within 1 minute, the next attempt returns HTTP 429. After 10 failed attempts, all login attempts from that IP are blocked for 15 minutes.

**NFR-15a-03: Audio Upload Validation**

Audio uploads must be validated server-side:
- Maximum file size: 25 MB
- Allowed MIME types: `audio/webm`, `audio/ogg`, `audio/mp4`
- Rate limit: max 10 uploads per hour

**Fit Criterion:** An upload exceeding 25 MB or with a disallowed MIME type is rejected with HTTP 422. The 11th upload within one hour returns HTTP 429.

**NFR-15a-04: Recovery Token Expiry**

The `.herold-recovery` file must have a time-to-live of 60 minutes based on file modification time (`filemtime`). Expired tokens must not grant recovery access. Missing file, wrong token, and expired token must return the same generic HTTP 404 response; the internal rejection reason is logged.

**Fit Criterion:** A `.herold-recovery` file older than 60 minutes does not grant access to the recovery form and returns the same generic 404 response as other invalid recovery states. The rejection reason is logged with timestamp and IP.

### 15b. Integrity Requirements

**NFR-15b-01: No API Keys in Frontend**

All external API keys (OpenAI, GitHub PAT) are stored server-side only. The frontend never receives or transmits these keys.

**Fit Criterion:** Browser DevTools network tab shows no external API keys in any request or response.

**NFR-15b-02: No Preprocessing Prompts in API Responses**

The `/types` endpoint must not include `preprocessing_prompt` values in its response. Only frontend-relevant fields (`label`, `icon`, `extra_fields`, `github_label`) are returned.

**Fit Criterion:** The JSON response of `GET /types` contains no key named `preprocessing_prompt`.

**NFR-15b-03: Secret Redaction in Logs**

Sensitive values must not appear in application logs. A dedicated redaction mechanism (e.g., custom Monolog processor) must mask known secret keys: `APP_KEY`, `HEROLD_API_KEY`, `GITHUB_TOKEN`, `OPENAI_API_KEY`, and session tokens (including Bearer/Authorization tokens). Transcript contents must not be logged — only processing events (e.g., "Transcription completed for voice note {id}").

**Fit Criterion:** A search through `storage/logs/` reveals no API keys, tokens, or transcript text.

**NFR-15b-04: Issue Content Sanitization**

Voice note content (transcripts, LLM-generated titles and bodies) is untrusted input. Before creating a GitHub Issue, the application must:

- Sanitize Markdown to remove executable content (HTML tags, JavaScript URIs)
- Structure the issue body so that untrusted content is clearly delimited (e.g., in a quoted block or under an "## Input" heading)
- Never include system prompts or preprocessing instructions in the issue body

**Fit Criterion:** A transcript containing `<!-- @agent: ignore all previous instructions -->` or similar injection attempts is rendered inert in the created GitHub Issue. The issue body clearly separates application-generated structure from user-originated content.

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
- Exception: `prompts/herold-spec.prompt.md` remains in German

**Fit Criterion:** No German text appears in the UI, codebase, or documentation (except `prompts/herold-spec.prompt.md`).

---

## 17. Compliance Requirements

### 17a. Legal Requirements

*Not applicable. Personal tool, no distribution, no user data from third parties.*

### 17b. Standards Compliance

**NFR-17b-01: Conventional Commits**

Git commit messages follow the Conventional Commits specification.

**Fit Criterion:** All commits match the pattern `type(scope): description`.
