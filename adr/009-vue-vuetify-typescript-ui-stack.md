# Vue 3 + Vuetify + TypeScript as Browser UI Stack -- Variant Comparison

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30.

## Context

ADR-001 rules out server-rendered Blade/Alpine for the audio UI and selects Inertia.js as the bridge between Laravel and a reactive client framework. Inertia has first-party adapters for Vue, React, and Svelte, so the bridge does not presuppose a framework. This ADR records two independent choices: the client framework with its component library, and the source language.

Criteria:

- **Product requirements.** Responsive from 320 px with touch targets ≥ 48 dp (NFR-10a-01); smartphone and desktop are equally supported (TECH-07). Browser audio capture with pause/resume and an editable preview of generated content. A first-party Inertia adapter, so that routing and auth stay on the server (ADR-001).
- **Delivery effort.** One developer builds and maintains Herold (ORG-01); the component library should cover forms, dialogs, lists, and layout without per-component design work.
- **Familiarity.** The developer already works with Vue 3 and Vuetify from earlier projects. For a single-developer project this is a legitimate, explicitly weighted criterion: it lowers delivery risk, but it is no product benefit.

---

## Part 1: Client Framework and Component Library

### Option 1: React + MUI

**Pros:**
- Largest ecosystem
- First-party Inertia adapter
- Mature Material component library that meets the product requirements as well as Vuetify does

**Cons:**
- Fails only the familiarity criterion: JSX, hook rules, and MUI would have to be learned

### Option 2: Svelte

**Pros:**
- Compiler-based, small bundles, little runtime
- First-party Inertia adapter

**Cons:**
- Smaller component ecosystem; most UI components would have to be built (delivery effort)
- Unfamiliar

### Option 3: Vue 3 + Tailwind CSS / Headless Components

**Pros:**
- Full visual control
- Small CSS output

**Cons:**
- Accessibility, touch sizing, focus handling, and responsive behaviour must be designed per component (delivery effort)

### Option 4: Vue 3 + Vuetify

**Concept:** Vue 3 Composition API in single-file components with Vuetify's Material Design 3 component set.

**Pros:**
- First-party Inertia adapter
- Responsive grid, touch-sized controls, and accessible components out of the box
- Familiar

**Cons:**
- Vuetify's Material visual language must be adapted rather than designed freely
- Vuetify major upgrades can require component migrations
- Larger CSS/JS payload than a utility-CSS approach

---

## Part 2: Source Language

### Option A: JavaScript

**Pros:**
- No type annotations to write or maintain
- Nothing to check beyond the build

**Cons:**
- The shapes of Inertia page props and of the browser-safe type projections exist only implicitly in controller code
- No editor support for prop and payload types

### Option B: TypeScript

**Concept:** `<script setup lang="ts">` in SFCs and shared `.ts` modules for the types crossing the Inertia boundary.

**Pros:**
- Props, page payloads, and type projections are explicit in the source
- Editor completion and refactoring support on those types

**Cons:**
- Vite only strips annotations; a separate type check (`vue-tsc`) is needed before types are enforced
- Types must be kept in step with the PHP side by hand

---

## Decision

Option 4 -- Vue 3 + Vuetify, written in TypeScript (Option B).

## Rationale

1. On the product requirements, React + MUI (Option 1) and Vue + Vuetify (Option 4) are equivalent. The decision between them rests on familiarity, which for a single developer without deadline pressure (ORG-01, ORG-05) is the cheapest way to lower delivery risk.
2. Svelte and the Tailwind/headless approach fail the delivery-effort criterion independently of familiarity: both leave the component work to the project.
3. Inertia supports Vue first-party; Laravel's Vite plugin and `@vitejs/plugin-vue` integrate the stack into one build (ADR-006); Vuetify is registered at application start.
4. TypeScript makes the data shapes crossing the Inertia boundary explicit. Its main benefit, enforced types, needs the separate type check that is still missing.

## Consequences

- All pages and components are Vue SFCs using Vuetify; design guidance lives in `DESIGN.md`.
- Type errors do not fail a build until a type check is added. The missing gate is tracked as technical debt D-09 (§ 11.2).
- Library versions are traceability information; `package-lock.json` is authoritative.
