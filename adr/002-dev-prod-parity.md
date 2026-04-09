# Dev/Prod Parity -- Variant Comparison

## Context

Herold runs on shared hosting in production (native PHP, Apache, FTP deployment,
limited SSH access). Local development uses Docker Compose. Two related questions
must be answered together:

1. **How closely should the Docker setup mirror production?**
   Differences between dev and prod are a common source of bugs that only surface
   after deployment. For a single developer deploying via FTP without the ability
   to debug on the server, minimizing these differences is critical.

2. **Should voice processing run synchronously or via an async queue?**
   The processing pipeline (Whisper transcription, LLM preprocessing, GitHub push)
   takes ~10-30 seconds. An async queue would require a worker mechanism in both
   dev and prod -- adding infrastructure complexity.

These questions are coupled: the choice of sync vs. async directly determines
whether cron infrastructure is needed in both environments.

---

## Option 1: Docker with nginx + PHP-FPM + persistent queue worker

**Concept:** Standard Docker setup with separate nginx and PHP-FPM containers.
Queue runs as a persistent `php artisan queue:work` process.

```yaml
services:
  app:    # PHP-FPM
  nginx:  # Webserver
  queue:  # php artisan queue:work (persistent)
  node:   # Vite dev server
```

**Pros:**
- Common Docker pattern, many tutorials and examples
- Queue processes jobs immediately (no delay)
- Non-blocking UI during processing

**Cons:**
- Production uses Apache, not nginx -- different rewrite rules, different config
- Production has no persistent process capability (shared hosting)
- `.htaccess` (Apache) is not tested in dev, but required in prod
- nginx.conf is dev-only configuration that adds maintenance burden
- Two significant dev/prod differences (webserver + queue mechanism)
- 4 containers for a single-user app

**Effort:** Medium. Requires nginx.conf, separate PHP-FPM config, job classes.

---

## Option 2: Docker with Apache + cron-based queue

**Concept:** Use `php:8.5-apache` image. Queue uses cron + `php artisan schedule:run`.
An HTTP-cron endpoint with Basic Auth handles queue processing in production
(shared hosting has no `crontab` access).

```yaml
services:
  app:    # PHP 8.5 + Apache (like prod)
  cron:   # crond → schedule:run (like prod)
  node:   # Vite dev server (dev only)
```

**Pros:**
- Dev and prod are identical: same webserver, same queue mechanism, same `.htaccess`
- `.htaccess` is tested in dev because Apache is used in dev
- No nginx.conf to maintain

**Cons:**
- Queue has up to 1-minute delay (cron interval)
- Requires cron Docker service + HTTP-cron endpoint + Basic Auth middleware
- Job classes, status polling, and 8-state NoteStatus enum for async tracking
- `CronController` + `VerifyCronAuth` middleware -- workaround for hosting limitations
- `php:8.5-apache` is a larger image than alpine-based alternatives
- 3 containers, additional infrastructure for a single-user demo app

**Effort:** Medium. Simpler than Option 1, but cron + HTTP endpoint add complexity.

---

## Option 3: Docker with Apache + synchronous processing

**Concept:** Use `php:8.5-apache` image for Apache parity. All processing
(transcription, LLM, GitHub push) runs synchronously in the HTTP request.
No queue, no cron, no worker.

```yaml
services:
  app:    # PHP 8.5 + Apache (like prod)
  node:   # Vite dev server (dev only)
```

**Pros:**
- Apache parity between dev and prod (same `.htaccess`, same rewrite rules)
- No queue infrastructure at all -- no cron service, no HTTP-cron endpoint,
  no `CronController`, no job classes, no status polling
- NoteStatus simplified to 4 states (`recorded`, `processed`, `sent`, `error`)
- 2 containers -- minimal setup
- Errors surface immediately in the response (no failed-job debugging)
- No cron configuration needed on shared hosting
- Drastically less code to build and maintain

**Cons:**
- Blocking request: user waits ~10-30 seconds for the full pipeline
- HTTP timeout risk on slow connections or large audio files
- No automatic retry on failure -- user must manually retry
- Not scalable for multi-user or high-volume scenarios

**Effort:** Low. Direct service calls in controllers, simple error handling.

---

## Option 4: Native local development (no Docker)

**Concept:** Install PHP 8.5, Composer, and Node.js locally. Use `php artisan serve`.

**Pros:**
- Closest possible to production (native PHP)
- No Docker overhead

**Cons:**
- Requires local PHP 8.5, Composer, Node.js installation
- Different OS (macOS vs Linux on hosting) may cause subtle issues
- Not reproducible across machines

**Effort:** Low to set up, but higher ongoing maintenance.

---

## Decision: Option 3 -- Apache + synchronous processing

**Rationale:**

1. **Apache parity where it matters.** The production webserver is Apache with
   `.htaccess` rewrite rules. Using the same `php:8.5-apache` image in dev
   ensures these rules are tested before FTP deployment. This eliminates
   the most common class of deployment bugs for shared hosting.

2. **Synchronous is sufficient.** Herold is a single-user demo project.
   The ~10-30 second wait for the processing pipeline is acceptable with
   a loading indicator. There is no concurrent usage that would benefit
   from background processing.

3. **Cron infrastructure is disproportionate.** An async queue would require:
   a cron Docker service, an HTTP-cron endpoint with Basic Auth (because
   shared hosting has no `crontab`), a `CronController` with auth middleware,
   three job classes, frontend status polling, and an 8-state enum for
   tracking job progress. This is significant infrastructure for a feature
   (non-blocking UI) that provides minimal value in a single-user context.

4. **Minimal setup.** Two Docker services (`app` + `node`), no cron
   configuration on the hosting side, no `CRON_USER`/`CRON_PASSWORD`
   environment variables. The simplest setup that achieves dev/prod parity.

5. **Future-compatible.** If Herold evolves beyond demo stage, the queue
   system can be introduced. The service layer (`AIService`, `GitHubService`,
   `PreprocessingService`) remains unchanged -- only the calling context
   changes (controller → job).

**Rejected alternatives:**
- **Option 1 (nginx + worker):** Two dev/prod differences (webserver + queue)
  with no compensating benefit. Persistent workers are not available on
  shared hosting.
- **Option 2 (Apache + cron):** Achieves full dev/prod parity, but the cron
  infrastructure is disproportionate for a single-user demo. The HTTP-cron
  workaround for shared hosting adds code that exists solely to compensate
  for platform limitations.
- **Option 4 (native):** Requires local installation of specific PHP version.
  Not reproducible across machines.

**Consequences:**
- Dockerfile uses `php:8.5-apache` base image, no `cron` package installed
- `docker-compose.yml` has 2 services: `app` and `node`
- No `cron` service, no `routes/console.php`, no scheduler configuration
- No `/cron/work` endpoint, no `CronController`, no `VerifyCronAuth` middleware
- No job classes -- controllers call services directly
- `NoteStatus` has 4 values: `recorded`, `processed`, `sent`, `error`
- Frontend shows loading indicator during synchronous processing
- `QUEUE_CONNECTION=sync` in production `.env`
- No `CRON_USER`/`CRON_PASSWORD` environment variables
