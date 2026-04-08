# Architecture Decisions

This document records key architecture decisions made for the project. Each decision documents the context, considered options, and rationale.

For detailed variant comparisons see [`adr/*.md`](../adr/).

---

## ADR-001: Inertia.js as Frontend Bridge (no separate API layer for the browser UI)

**Status:** Accepted

**Context:** Herold is a Laravel monolith with a Vue frontend and two consumer types: a human (browser) and AI agents (CLI/curl). The question was how Laravel and Vue should communicate.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- SPA + JSON API** | Vue as standalone SPA, Laravel serves only JSON. Own router, own auth in frontend. | Clear separation, one API for all | Duplicate routing logic, frontend auth overhead, CORS, more boilerplate |
| **B -- Blade + Alpine.js** | Server-side rendering with Blade, Alpine for interactivity. | Simplest setup, no JS build | Audio UI too complex for Alpine, no SPA feeling |
| **C -- Inertia.js** | Laravel routing + auth + validation, Vue only for rendering. SPA feeling without a separate API layer. | One routing system, no frontend auth overhead, Vue experience reusable | Agents need separate API routes, additional concept |

**Decision:** Option C -- Inertia.js.

**Rationale:** A monolith does not need a separate API layer for the browser UI. Inertia eliminates duplicate routing, frontend auth, and manual error handling. The complex audio UI (MediaRecorder, waveform) requires Vue -- Blade would not suffice. Agents get a separate API (`api.php` + Sanctum), the browser UI runs via Inertia (`web.php` + session). Clean separation, minimal overhead. Detailed variant comparison: [`adr/001-inertia-frontend-bridge.md`](../adr/001-inertia-frontend-bridge.md).

---

## ADR-002: Dev/Prod Parity -- Apache + Cron in Docker

**Status:** Accepted

**Context:** Production runs on shared hosting (Apache, cron, FTP, no shell). The Docker dev setup must minimize dev/prod drift to prevent "works on my machine" bugs that only surface after FTP deployment.

**Options:**

| Option | Description | Pros | Cons |
|--------|------------|------|------|
| **A -- nginx + PHP-FPM + worker** | Standard Docker pattern. Persistent queue worker. | Common pattern, instant job processing | Two dev/prod differences (webserver + queue). `.htaccess` untested in dev. |
| **B -- Apache + cron** | `php:8.5-apache` image. Cron-based queue like prod. | Zero dev/prod drift, fewer containers, `.htaccess` tested | Up to 1-min queue delay, larger image |
| **C -- Native (no Docker)** | Local PHP + Composer + Node. | Closest to prod | Requires local installation, not reproducible |

**Decision:** Option B -- Apache + cron in Docker.

**Rationale:** Every component that matters (Apache, cron queue, `.htaccess`) is identical in dev and prod. This eliminates the most common deployment bugs for shared hosting. Simpler setup (3 services instead of 4, no nginx.conf). The 1-minute queue delay is acceptable for a single-user app. Detailed variant comparison: [`adr/002-dev-prod-parity.md`](../adr/002-dev-prod-parity.md).
