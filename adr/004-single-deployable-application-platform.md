# Laravel Monolith as a Single-Deployable Application Platform -- Variant Comparison

## Context

Herold is a personal, single-operator application that must run on existing shared hosting. The host provides Apache and PHP, but no container runtime, background workers, or separate application services. The product still needs a reactive browser UI for audio capture and review.

The decision is about the application topology and deployment unit. The communication mechanism between Laravel and Vue is decided separately in ADR-001; Apache parity and synchronous processing are decided in ADR-002.

---

## Option 1: Independently Deployed Backend and Frontend

**Concept:** Laravel exposes a JSON API and a separately deployed SPA owns browser routing, authentication state, and API communication.

**Pros:**
- Independent release cycles
- Clear network boundary between backend and frontend
- Either side could be replaced separately

**Cons:**
- Two deployment units on a host designed for one PHP application
- Duplicate routing and browser-side authentication concerns
- CORS and API-versioning overhead
- No operational benefit for one developer and one operator

---

## Option 2: Multiple Backend Services

**Concept:** Split capture, AI processing, persistence, and dispatch into separately deployed services.

**Pros:**
- Strong runtime isolation
- Independent scaling and technology choices

**Cons:**
- Shared hosting cannot run or supervise the required services
- Introduces network failure modes and distributed deployment
- Disproportionate for a single-user workload

---

## Option 3: One Laravel Application with an Integrated Browser UI

**Concept:** Laravel owns routing, authentication, orchestration, integrations, and persistence. Vue/Inertia pages and Vuetify components live in the same repository and are compiled into the same release artefact. The JavaScript executes in the browser, but is not a separately operated system.

**Pros:**
- One codebase, release artefact, and production installation
- Laravel supplies routing, sessions, validation, Eloquent, and operational conventions
- Reactive Vue UI remains available for browser audio APIs
- Fits the capabilities and cost constraints of the target host

**Cons:**
- Backend and browser UI release together
- The application is tied to the PHP/Laravel ecosystem
- Horizontal decomposition would require a later architectural change

---

## Decision: Option 3 -- One Laravel Application

Herold is implemented as a Laravel monolith and delivered as one deployable application. The browser UI is part of that application through the Inertia bridge selected by ADR-001. Logical layers remain explicit inside the codebase, but they are not independent runtime services.

## Rationale

1. The production environment naturally hosts one Apache/PHP application.
2. A single deployable minimises operational work and failure modes for a personal tool.
3. Laravel covers the server-side concerns Herold actually needs without additional platform components.
4. Vue and Vuetify provide the reactive browser surface needed for recording and editing while retaining one release unit.
5. Internal adapter boundaries preserve replaceability where it matters without paying for distributed deployment.

## Consequences

- PHP and Laravel are binding platform choices for the server-side architecture.
- The UI source uses Vue, Inertia, and Vuetify in the same repository; ADR-001 governs the bridge and the absence of a separate browser API.
- Server and UI changes are versioned and released together.
- Built frontend assets are included in the production artefact; ADR-006 decides where and how they are built.
- Framework and library versions are traceability information, not permanent architecture constraints. `composer.lock` and `package-lock.json` are authoritative for installed versions.
- Adding an independently deployed service requires a new decision that demonstrates why the monolith boundary is no longer sufficient.
