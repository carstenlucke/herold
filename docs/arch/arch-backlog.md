# Architecture Backlog

Open architecture items awaiting ADR work. Items here originated as proposals, in-progress decisions, or material that was misclassified in the spec and needs to be reabsorbed at the architecture layer.

## Removed from `docs/spec/P1-constraints.md` (Volere § 3a / 3b / 3d) — pending ADRs

The following items were originally written as Volere *Mandated Constraints*. They are in fact design decisions taken inside the constraint space, not externally imposed limits, and were therefore moved out of the spec to keep it implementation-free. Their substance and rationale are preserved below until each is captured as a proper ADR.

### Application platform (former CON-3a-01)

- **Decision:** Laravel 13 monolith on PHP 8.5; single codebase, single deployment.
- **Rationale:** Minimal operational complexity for a personal tool. No microservices, no separate frontend deployment.
- **Action:** Extract into a dedicated ADR (e.g. `004-application-platform.md`).

### Frontend stack details (former CON-3a-02)

- **Decision:** Inertia.js 3 + Vue 3.5 + TypeScript 6 + Vuetify 4 (MD3 / CSS Cascade Layers).
- **Rationale:** The architectural bridge (Inertia) is already covered by ADR-001. The remaining stack details (Vue, TypeScript, Vuetify version) are implementation choices.
- **Action:** Fold version/stack details into the application-platform ADR, or add a dedicated frontend-stack ADR.

### Database product (informs CON-3a-03)

- **Decision:** SQLite as the sole database; single file on the persistent host surface ([S3.3](../spec/S3-inbetriebnahme.md#s33-persistent-state-surfaces)).
- **Rationale:** Satisfies CON-3a-03 (no separate server, single file is portable, well within single-user write load).
- **Open architecture detail:** singleton trigger for the operator row, journal mode, file location, backup considerations. Cross-reference with [`docs/arch/TODO.md`](TODO.md) (the "users-singleton trigger" item under the relational data model).
- **Action:** Capture in a database ADR.

### Build toolchain (former CON-3a-05)

- **Decision:** Vite 8 with the Rolldown bundler; Node.js 24 LTS at build time only, not at runtime.
- **Rationale:** Standard Laravel frontend toolchain. Off-host build keeps the production host free of build tooling and is the basis for the deterministic-artefact statement in [S3.5](../spec/S3-inbetriebnahme.md#s35-ongoing-releases).
- **Action:** Capture in the application-platform ADR or a dedicated build-toolchain ADR.

### Local development environment (former CON-3b-02)

- **Decision:** Docker Compose for local development only. Services: `app` (PHP 8.5 + Apache), `node` (Vite dev server). Mirrors production runtime; not deployed.
- **Rationale:** Reproducible dev environment with zero dev/prod drift. Shared hosting does not support Docker (CON-3b-01). ADR-002 already records the dev/prod parity *decision*; the dev-tooling choice itself is not yet captured.
- **Action:** Extract into a dev-environment ADR (or fold into ADR-002).

### Key dependency versions (former CON-3d-01)

- **Material captured for reference:**
  - `laravel/ai` (0.4.x) — pre-1.0, API may change
  - `laragear/two-factor` (4.0) — must stay compatible with Laravel 13
  - `inertiajs/inertia-laravel` (3.0) — must match client version
  - `@inertiajs/vue3` (3.0) — must match server version
  - `vuetify` (4.0) — breaking change from v3 (MD3)
- **Rationale:** A spec-level constraint table is the wrong home for dependency versions. `composer.json` / `package.json` are authoritative; rationale belongs in the relevant ADRs.
- **Action:** Surface noteworthy version constraints (e.g. `laravel/ai` pre-1.0 API risk) in the relevant ADR risk sections.

### Provider-abstracting AI client (informs CON-3c-01)

- **Decision:** Laravel AI SDK (`laravel/ai`) as the configuration-switchable AI consumption layer.
- **Rationale:** CON-3c-01 mandates Whisper + Chat Completion as the AI providers and requires provider-switchability; the concrete client realises that property.
- **Action:** Capture the SDK choice and the provider-portability boundary in an ADR (links to N2 cross-cutting concept on AI provider portability).
