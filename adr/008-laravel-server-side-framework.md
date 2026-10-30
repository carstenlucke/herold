# Laravel as Server-Side Framework -- Variant Comparison

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30.

## Context

The production host provides Apache and PHP as the only server-side runtime (TECH-01). PHP is therefore given, not chosen. ADR-004 decides that Herold is one deployable application but leaves open which framework structures it.

Herold's server side needs routing, session authentication with CSRF protection, request validation, relational persistence with migrations on SQLite (ADR-005), an HTTP client for the OpenAI and GitHub APIs (ADR-007), encrypted model attributes for the TOTP secret, rate limiting, and a server-side adapter for the Inertia bridge (ADR-001). The framework ships inside the release artefact; the host must not need anything beyond PHP and its standard extensions.

---

## Option 1: Plain PHP or a Micro-Framework (e.g. Slim)

**Concept:** A small front controller with hand-picked components for routing, sessions, and persistence.

**Pros:**
- Smallest code and dependency footprint
- No framework conventions to follow
- Full control over every request step

**Cons:**
- Session handling, CSRF, validation, migrations, encryption, rate limiting, and test support must be assembled and kept consistent by hand
- No first-party Inertia adapter; the protocol would have to be implemented or adopted from a community package
- Security-relevant plumbing becomes project code

---

## Option 2: Symfony

**Concept:** Full-stack framework built from Symfony components, Doctrine for persistence.

**Pros:**
- Mature, well-documented components with long-term support releases
- Explicit configuration and dependency injection
- Runs on Apache + PHP without additional services

**Cons:**
- Encrypted entity attributes need a third-party Doctrine extension; rate limiting, the HTTP client and its test doubles are separate components to install and wire via service configuration
- Inertia support is community-maintained
- Doctrine migrations and entity mapping are heavier than Herold's small schema needs

---

## Option 3: Laravel

**Concept:** Full-stack framework with Eloquent, migrations, HTTP client, encryption, rate limiting, and first-party Inertia and Vite integrations.

**Pros:**
- Every concern listed above is built in and integrated
- First-party `inertia-laravel` adapter and `laravel-vite-plugin`
- Runs on plain Apache + PHP via the standard `public/.htaccess` front controller
- First-class SQLite support for Eloquent, migrations, sessions, cache, and tests
- HTTP client with fakes makes the external integrations testable without network access

**Cons:**
- Large `vendor/` tree in every release upload (FTP, see ORG-07)
- Annual major releases require periodic upgrade work
- Operational tasks use `artisan` (e.g. manual migrations, ORG-06), binding operations to the framework

---

## Decision: Option 3 -- Laravel

Laravel is the server-side framework. It is delivered with the application in `vendor/`; the host provides only PHP.

## Rationale

1. Laravel covers every server-side concern Herold needs without assembling further platform components.
2. It runs on the host as delivered: Apache rewrites to `public/index.php`, no daemon, only standard PHP extensions (listed in § 7.1.1 of the deployment view; `composer.lock` is authoritative).
3. The first-party Inertia adapter and Vite plugin make the UI stack of ADR-001 and ADR-009 a supported configuration rather than an integration project.
4. Built-in fakes for the HTTP client keep the integration seams of ADR-007 and the GitHub dispatch testable.

## Consequences

- Laravel is a binding platform choice for the server-side architecture; replacing it would touch every server-side building block.
- The release artefact includes `vendor/` (ADR-006, § 7.2).
- Framework versions are traceability information; `composer.lock` is authoritative. Major upgrades are maintenance unless they change a documented boundary.
- Operator procedures that use `artisan` (migrations, first-commissioning bootstrap) need a PHP CLI. The host offers shell access only optionally (CON-3b-01); without it, these procedures run off-host against a copy of the SQLite file that is transferred via FTP. The risk and the fallback are recorded as R-08 (§ 11.1).
