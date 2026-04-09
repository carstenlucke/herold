# Dev/Prod Parity -- Variant Comparison

> **Note:** Partially superseded by [ADR-004](004-synchronous-processing.md).
> The cron-based queue has been removed in favor of synchronous processing.
> The Apache parity argument remains valid. Docker Compose now has 2 services
> (`app` + `node`) instead of 3.

## Context

Herold runs on shared hosting in production (native PHP, Apache, cron, FTP deployment).
Local development uses Docker Compose. The question: how closely should the Docker
setup mirror production?

Differences between dev and prod environments are a common source of bugs that only
surface after deployment ("works on my machine"). For a single developer deploying
via FTP without the ability to debug on the server (no shell access), minimizing
these differences is critical.

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
- Queue processes jobs immediately (no 1-minute delay)

**Cons:**
- Production uses Apache, not nginx — different rewrite rules, different config
- Production uses cron-based queue, not a persistent worker — different timing behavior
- `.htaccess` (Apache) is not tested in dev, but required in prod
- nginx.conf is dev-only configuration that adds maintenance burden
- Two significant dev/prod differences (webserver + queue mechanism)

**Effort:** Medium. Requires nginx.conf, separate PHP-FPM config.

---

## Option 2: Docker with Apache + cron-based queue (mirrors production)

**Concept:** Use `php:8.5-apache` image which combines PHP + Apache in one container.
Queue uses cron + `php artisan schedule:run`, identical to production.

```yaml
services:
  app:    # PHP 8.5 + Apache (like prod)
  cron:   # crond → schedule:run (like prod)
  node:   # Vite dev server (dev only)
```

**Pros:**
- Dev and prod are identical: same webserver, same queue mechanism, same `.htaccess`
- No "works on my machine" risk
- Fewer containers (no separate nginx + php-fpm)
- `.htaccess` is tested in dev because Apache is used in dev
- No nginx.conf to maintain

**Cons:**
- Queue has up to 1-minute delay (cron interval) — slightly slower feedback during development
- `php:8.5-apache` is a larger image than alpine-based alternatives
- Less common Docker pattern (most tutorials use nginx)

**Effort:** Low. Simpler setup, fewer config files.

---

## Option 3: Native local development (no Docker)

**Concept:** Install PHP 8.5, Composer, and Node.js locally. Use `php artisan serve`
and the built-in PHP development server.

**Pros:**
- Closest possible to production (native PHP)
- No Docker overhead

**Cons:**
- Requires local PHP 8.5, Composer, Node.js installation
- Different OS (macOS vs Linux on hosting) may cause subtle issues
- No reproducible environment — depends on local machine setup
- Cron setup on macOS differs from Linux

**Effort:** Low to set up, but higher ongoing maintenance.

---

## Decision: Option 2 -- Apache + cron in Docker

**Rationale:**

1. **Zero dev/prod drift.** Every component that matters — webserver (Apache),
   queue mechanism (cron → `schedule:run`), PHP version, `.htaccess` rewrite rules —
   is identical in dev and prod. This eliminates the most common class of
   deployment bugs for shared hosting.

2. **Simpler setup.** `php:8.5-apache` combines PHP and Apache in one image.
   No separate nginx container, no `nginx.conf` to maintain, no PHP-FPM socket
   configuration. Three services instead of four.

3. **`.htaccess` is tested in dev.** Laravel ships with a `.htaccess` for Apache.
   With nginx in dev, this file is never exercised — problems only surface in prod.
   With Apache in dev, the exact same rewrite rules run in both environments.

4. **Acceptable trade-off.** The 1-minute queue delay (cron interval) is a minor
   inconvenience during development. For a single-user app processing voice notes,
   this is not a meaningful bottleneck. If faster feedback is needed during
   development, `php artisan queue:work` can be run manually in the container.

**Rejected alternatives:**
- **Option 1 (nginx + worker):** Two significant dev/prod differences with no
  compensating benefit. The "standard Docker pattern" argument is irrelevant
  when it introduces deployment risk.
- **Option 3 (native):** Requires local installation of specific PHP version.
  Not reproducible across machines.

**Consequences:**
- Dockerfile uses `php:8.5-apache` base image
- No `nginx.conf` or `.docker/` directory needed
- Queue worker service replaced by cron service
- `docker-compose.yml` has 3 services: `app`, `cron`, `node`
- For faster queue processing during dev: `docker compose exec app php artisan queue:work`
