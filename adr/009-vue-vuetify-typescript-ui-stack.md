# Vue 3 + Vuetify + TypeScript as Browser UI Stack -- Variant Comparison

## Context

ADR-001 rules out server-rendered Blade/Alpine for the audio UI and selects Inertia.js as the bridge between Laravel and a reactive client framework. It does not compare client frameworks, component libraries, or the source language.

Requirements that shape the choice:

- Responsive from 320 px with touch targets ≥ 48 dp (NFR-10a-01); smartphone and desktop are equally supported (TECH-07).
- Browser audio capture with pause/resume and an editable preview of generated content.
- A first-party Inertia adapter, so that routing and auth stay on the server (ADR-001).
- One developer; the component library should cover forms, dialogs, lists, and layout without per-component design work.

---

## Option 1: React + MUI

**Pros:**
- Largest ecosystem
- First-party Inertia adapter
- Mature Material component library

**Cons:**
- Existing Vue 3 + Vuetify experience (ADR-001) would not be reused
- JSX component style and hook rules add a learning curve without a product benefit

---

## Option 2: Svelte

**Pros:**
- Compiler-based, small bundles, little runtime
- First-party Inertia adapter

**Cons:**
- Smaller component ecosystem; most UI components would have to be built
- Existing Vue 3 + Vuetify experience (ADR-001) would not be reused

---

## Option 3: Vue 3 + Tailwind CSS / Headless Components

**Pros:**
- Full visual control
- Small CSS output

**Cons:**
- Accessibility, touch sizing, focus handling, and responsive behaviour must be designed per component
- More UI code to own for a single developer

---

## Option 4: Vue 3 + Vuetify

**Concept:** Vue 3 Composition API in single-file components, Vuetify's Material Design 3 component set, TypeScript in `<script setup lang="ts">` and in shared `.ts` modules.

**Pros:**
- First-party Inertia adapter
- Vue 3 Composition API and Vuetify are known from earlier work (ADR-001)
- Responsive grid, touch-sized controls, and accessible components out of the box
- TypeScript documents props, page payloads, and the browser-safe type projections at the Inertia boundary

**Cons:**
- Vuetify's Material visual language must be adapted rather than designed freely
- Vuetify major upgrades can require component migrations
- Larger CSS/JS payload than a utility-CSS approach

---

## Decision: Option 4 -- Vue 3 + Vuetify, written in TypeScript

## Rationale

1. Existing Vue 3 + Vuetify experience makes the UI productive from the start (ADR-001).
2. Vuetify's components meet the responsive and touch-target requirements without per-component design work.
3. Inertia supports Vue first-party; Laravel's Vite plugin and `vite-plugin-vuetify` integrate the stack into one build (ADR-006).
4. TypeScript makes the data shapes crossing the Inertia boundary explicit in the source.

## Consequences

- All pages and components are Vue SFCs using Vuetify; design guidance lives in `DESIGN.md`.
- TypeScript is transpiled by Vite, which strips type annotations without checking them. There is currently no `typescript`/`vue-tsc` dependency and no `tsconfig.json`, so type errors do not fail a build. Adding `vue-tsc` as a CI gate is tracked in [issue #53](https://github.com/carstenlucke/herold/issues/53).
- Library versions are traceability information; `package-lock.json` is authoritative.
