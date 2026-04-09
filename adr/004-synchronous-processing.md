# Synchronous Processing -- Variant Comparison

## Context

Herold's voice processing pipeline has three steps: transcription (OpenAI Whisper),
LLM preprocessing (structured ticket from transcript), and GitHub Issue creation.
The original design used Laravel's queue system with cron-based workers to process
these steps asynchronously in background jobs.

This introduced currently unwanted complexity:
- A dedicated cron Docker service for local development
- An HTTP-cron endpoint (`/cron/work`) with Basic Auth for production (shared hosting
  has no shell access for `crontab`)
- `CronController` with `VerifyCronAuth` middleware
- Job classes (`TranscribeAudioJob`, `PreprocessTranscriptJob`, `CreateGitHubIssueJob`)
- Status polling in the frontend (Inertia `router.reload()` every 2 seconds)
- Error handling and retry logic across job boundaries
- `NoteStatus` enum with 8 granular states to track job progress

The question: is this complexity justified for a single-user demo project?

---

## Option 1: Asynchronous queue processing with cron workers

**Concept:** Jobs are dispatched to Laravel's database queue. A cron-based worker
processes them every minute. The frontend polls for status updates.

**Pros:**
- Non-blocking UI -- user can navigate away while processing runs
- Retry logic built into Laravel's job system (`--tries=3`)
- Granular error tracking (which job failed?)
- Scalable pattern for future growth

**Cons:**
- Cron service in Docker adds a container and configuration
- HTTP-cron endpoint on shared hosting requires Basic Auth, a dedicated controller,
  and middleware -- all for triggering `queue:work`
- Up to 1-minute delay before jobs start processing
- Frontend needs polling or WebSocket for status updates
- 8-state `NoteStatus` enum to track job transitions
- Three separate job classes with dispatching logic
- Job failures require manual retry or inspection
- Shared hosting cron is limited (HTTPS calls only, no `crontab`)

**Effort:** High -- cron service, HTTP endpoint, job classes, polling, error handling.

---

## Option 2: Synchronous processing in the request lifecycle

**Concept:** All three steps (transcribe, preprocess, create GitHub Issue) run
synchronously in a single HTTP request when the user triggers processing.
The browser waits for the response.

**Pros:**
- No queue, no cron, no worker -- drastically simpler architecture
- No polling or WebSocket -- the response IS the result
- No cron Docker service, no HTTP-cron endpoint, no `CronController`
- No job classes -- service calls directly in the controller
- `NoteStatus` simplified (no intermediate `transcribing`/`processing`/`sending` states)
- Fewer failure modes -- errors surface immediately in the response
- Docker Compose reduced from 3 services to 2 (`app` + `node`)
- Production deployment simplified: no cron configuration on shared hosting

**Cons:**
- Blocking request: user waits ~10-30 seconds for the full pipeline
- HTTP timeout risk on slow connections or large audio files (mitigated by PHP/Apache timeout config)
- No automatic retry on failure -- user must manually retry
- Browser shows loading state during processing
- Not scalable for multi-user or high-volume scenarios

**Effort:** Low -- direct service calls, simple error handling.

---

## Option 3: Synchronous with deferred GitHub push

**Concept:** Transcription and LLM preprocessing run synchronously. The GitHub
Issue creation is deferred to a separate user action ("Send to GitHub" button).

**Pros:**
- User gets fast feedback on transcription and preprocessing
- GitHub push is a conscious decision (review before sending)
- Partial failure is easier to handle (transcript saved even if GitHub fails)

**Cons:**
- Two-step UX adds friction for the common case
- Still synchronous -- user waits for transcription + LLM
- The "review before sending" step already exists in the current UX design

**Effort:** Low -- similar to Option 2, but with an extra controller action.

---

## Decision: Option 2 -- Synchronous processing

**Rationale:**

1. **Demo project scope.** Herold is a single-user demo project in an early stage.
   The async queue infrastructure (cron service, HTTP endpoint, job classes, polling)
   adds substantial complexity for a scenario that does not require it.

2. **Acceptable wait time.** The full pipeline (Whisper transcription + LLM chat +
   GitHub API call) takes roughly 10-30 seconds. For a single user processing
   one voice note at a time, this is a reasonable wait with a loading indicator.

3. **Shared hosting constraints.** The production environment has limited cron
   support (HTTPS calls only, no `crontab`). The HTTP-cron workaround
   (`/cron/work` with Basic Auth) was an engineering effort to compensate for
   platform limitations. Synchronous processing eliminates this problem entirely.

4. **Complexity reduction.** Removing the queue system eliminates:
   - `cron` Docker service
   - `CronController` + `VerifyCronAuth` middleware
   - `TranscribeAudioJob`, `PreprocessTranscriptJob`, `CreateGitHubIssueJob`
   - Status polling in the frontend
   - Intermediate `NoteStatus` states (`transcribing`, `processing`, `sending`)
   - `CRON_USER`/`CRON_PASSWORD` environment variables
   - `routes/console.php` scheduler configuration

5. **Future-compatible.** If Herold evolves beyond demo stage and needs async
   processing (e.g., for batch operations or multi-user support), the queue
   system can be reintroduced. The service layer (`AIService`, `GitHubService`)
   remains the same -- only the calling context changes (controller vs. job).

**Rejected alternatives:**
- **Option 1 (async queue):** Disproportionate complexity for a single-user demo.
  The cron infrastructure solves a scaling problem that does not exist.
- **Option 3 (deferred GitHub push):** The existing UX already has a review step
  before sending. Splitting into two synchronous requests adds UX friction
  without meaningful benefit.

**Consequences:**
- Docker Compose has 2 services: `app` (PHP + Apache) and `node` (Vite dev server)
- No `cron` service, no `routes/console.php` scheduler
- No `/cron/work` endpoint, no `CronController`, no `VerifyCronAuth` middleware
- No `CRON_USER`/`CRON_PASSWORD` in `.env`
- Processing triggered by controller actions calling services directly
- `NoteStatus` simplified: `recorded` → `processed` → `sent` → `error`
- Frontend shows loading indicator during synchronous processing (no polling)
- Dockerfile no longer installs `cron` package
- ADR-002 (Dev/Prod Parity) is partially superseded: the cron-parity argument
  no longer applies, but Apache parity remains valid
