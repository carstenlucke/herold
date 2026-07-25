# Off-Host Frontend Build with Vite -- Variant Comparison

## Context

Herold's Vue browser UI requires compilation and bundling. The production shared host runs PHP and Apache but does not provide Node.js or a reliable build environment. Releases are uploaded as files over FTPS, so the production artefact must already contain everything needed at runtime.

---

## Option 1: Build on the Production Host

**Pros:**
- Source and generated assets are assembled in the target environment
- Small upload before the build

**Cons:**
- Node.js and package installation are unavailable on the host
- Builds would be non-repeatable and mutate production in place
- Failed builds could leave a partial deployment

---

## Option 2: Commit Generated Assets

**Pros:**
- Production needs no build tooling
- Releases can upload the repository directly

**Cons:**
- Generated files create noisy diffs and merge conflicts
- Source and generated assets can drift
- Reproducibility depends on developers remembering to rebuild

---

## Option 3: Build Off-Host and Deploy Static Assets

**Concept:** Vite runs in development and CI. Release automation installs locked npm dependencies, builds `public/build/`, and uploads the result with the PHP application and production Composer dependencies.

**Pros:**
- Production contains no Node runtime or package manager
- `npm ci` and the lockfile provide a repeatable dependency set
- CI can reject source that does not build
- Generated assets remain outside version control

**Cons:**
- Release automation is responsible for assembling a complete artefact
- Development uses a Vite server while production uses static assets
- Node/Vite upgrades can affect the release pipeline

---

## Decision: Option 3 -- Build Off-Host with Vite

Vite is Herold's frontend build tool. Node.js is a development and CI dependency only. Production receives the generated assets under `public/build/`; it never runs Node.js or Vite.

## Rationale

1. The target host cannot and should not build frontend assets.
2. Building from lockfiles in CI gives a deterministic gate before deployment.
3. Keeping generated output out of Git avoids source/build drift in commits.
4. Vite is the standard integration point for the selected Laravel/Vue stack and provides the development HMR server.

## Consequences

- Local development runs the Vite server in the Compose `node` service.
- CI runs `npm ci` and `npm run build`; a failed build blocks delivery.
- Tagged releases build assets before the FTPS mirror begins.
- `public/build/` is part of the deployment artefact; `node_modules/`, package manifests, Vite configuration, and the Node runtime are not required in production.
- Node, Vite, Vue, and plugin versions are governed by `package.json` and `package-lock.json`; version upgrades are routine maintenance unless they change this build boundary.
- Replacing Vite is possible without changing the runtime architecture as long as the replacement still produces static assets off-host.
