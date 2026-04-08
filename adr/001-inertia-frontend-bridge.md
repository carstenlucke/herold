# Inertia.js as Frontend Bridge -- Variant Comparison

## Context

Herold is a Laravel 13 monolith with a Vue 3 frontend. The central question: how do Laravel (server) and Vue (client) communicate? The app serves two consumer types: a human user (browser) and AI agents (CLI/curl). The architecture must support both without duplicating code.

---

## Option 1: Classic SPA with Separate JSON API

**Concept:** Laravel serves JSON endpoints exclusively. Vue is a standalone SPA with its own router (vue-router), its own auth management (tokens/cookies), and its own state management for server data.

**Pros:**
- Clear separation: backend = API, frontend = SPA
- A single API for both browser and agents
- Frontend could theoretically be swapped to a different backend

**Cons:**
- Duplicate routing logic (Laravel + vue-router)
- Auth must be managed separately in the frontend (token handling, interceptors, redirect logic)
- Validation errors must be manually transferred from JSON responses to the UI
- CORS configuration required (even locally)
- More boilerplate: every page needs an API call, loading state, error state

**Effort:** High -- significant overhead from decoupling, even though frontend and backend live in the same repo.

---

## Option 2: Blade Templates (Server-Side Rendering)

**Concept:** Laravel renders HTML server-side with Blade templates. Interactivity via Alpine.js or Livewire for reactive components.

**Pros:**
- No JavaScript build step required (with pure Blade + Alpine)
- Simplest setup
- SEO-friendly (irrelevant for Herold)
- Fast initial load time

**Cons:**
- Audio recording (MediaRecorder API, waveform visualization) requires substantial JavaScript -- Alpine.js will hit its limits here
- No SPA feeling: every navigation is a full-page reload
- No reuse of Vue experience from the StudPlus project
- Livewire would provide reactivity, but complex browser APIs (MediaRecorder, AnalyserNode) remain pure JavaScript

**Effort:** Low for simple pages, high for the audio recording UI.

---

## Option 3: Inertia.js (Laravel Routing + Vue Rendering)

**Concept:** Inertia.js connects Laravel controllers directly to Vue components. Laravel handles routing, auth, validation, and redirects. Vue renders the pages -- like Blade, but with Vue components. No separate API layer needed for the browser UI.

**Pros:**
- One routing system (Laravel `web.php`) instead of two
- Auth, session, CSRF, validation -- all standard Laravel, no frontend overhead
- Validation errors are automatically passed as props to the Vue page
- SPA feeling without full-page reloads
- Vue experience from StudPlus project directly reusable
- No CORS needed (same origin)
- Significantly less boilerplate than a classic SPA

**Cons:**
- Agents cannot use Inertia endpoints directly (Inertia responses are not plain JSON)
- Requires separate `api.php` routes for the agent API (Sanctum token auth)
- Inertia is an additional concept that needs to be understood
- Server-side rendering (SSR) is possible but more complex than with a pure Vue SPA

**Effort:** Medium -- initial setup for Inertia + Vue + Vuetify, then very productive development.

---

## Decision: Option 3 -- Inertia.js

**Rationale:**

1. **One monolith, one system for routing/auth:** Laravel controls the entire request lifecycle. No duplicate routing logic, no frontend auth management, no manual API calls for the UI. This significantly reduces complexity compared to Option 1.

2. **Vue for complex UI:** Audio recording (MediaRecorder, waveform, pause/resume) and the interactive preview (editable transcript, status polling) require a reactive frontend framework. Blade + Alpine (Option 2) would hit its limits.

3. **Clean separation browser vs. agent:** The browser UI runs via Inertia (`web.php`, session auth). The agent API runs via separate endpoints (`api.php`, Sanctum token auth). No mixing, no compromises.

4. **Existing experience:** Vue 3 Composition API + Vuetify are known from the StudPlus project. Inertia eliminates the part that was most effort-intensive there (API layer + Axios interceptors + frontend auth).

5. **Less code:** A typical controller call with Inertia:
   ```php
   return Inertia::render('Notes/Show', ['note' => $note]);
   ```
   No separate API endpoint, no `axios.get()` in the frontend, no loading state management.

**Rejected alternatives:**
- **Option 1 (SPA + API):** Too much overhead for a single-user tool. Decoupling provides no benefit since frontend and backend live in the same repo and deployment.
- **Option 2 (Blade):** Unsuitable for the complex audio UI. Would inevitably lead to a mix of Blade + extensive vanilla JS.

**Consequences:**
- Browser UI routes in `web.php` with `Inertia::render()`
- Agent API routes in `api.php` with JSON responses + Sanctum
- Vue pages under `resources/js/Pages/` follow the controller structure
- No vue-router -- navigation via `<Link>` and `router.visit()` from Inertia
- Forms via `useForm()` from Inertia (automatic validation, CSRF, redirects)
