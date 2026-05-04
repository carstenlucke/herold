# Herold — Specification

Specification of Herold structured according to the building-block model of **Johannes Siedersleben**. Each block captures one well-defined view on the system and lives in its own file. This document is the orchestrator: it introduces the model, indexes all blocks, and records which blocks are not applicable.

The reference description of the building-block model is in [`SIEDERSLEBEN.md`](SIEDERSLEBEN.md). Source: SIEDERSLEBEN, J. (ed.) 2003. *Softwaretechnik — Praxiswissen für Softwareingenieure.* München: Carl Hanser Verlag.

---

## E1 — Reading Guide

### Audience

- The operator (single user) — to understand what Herold does and why.
- Future maintainers (humans or AI agents) — to navigate the codebase through a stable conceptual map.
- Reviewers — to assess scope, constraints, and decisions independently of the source code.

### How to read

1. Start with **P1** — goals, scope, constraints, success criteria.
2. Continue with **P2** — the structural skeleton that frames everything else.
3. Read **F1–F3** for the functional view (processes, use cases, functions).
4. Use **D1–D2** as the data reference while reading any other block.
5. Consult **B1** for UI specifics, **S1/S3** for integration and rollout, **N1/N2** for cross-cutting qualities.
6. **E2** is a glossary — look up terms as needed.

### Conventions

- Blocks are identified by Siedersleben's two-letter codes (`P1`, `F2`, …).
- One file per block, named `<code>-<topic>.md`.
- The specification describes **what** the system is and **why**, not implementation detail. Architecture-level and code-level decisions live in [`docs/arch/`](../arch/) (ADRs and the dedicated architecture document).
- Language: English. The German block names from Siedersleben are kept for traceability; content is written in English.

### Status legend for the index below

| Symbol | Meaning |
|--------|---------|
| ✅ | Block exists in this directory. |
| 🛠 | Block is planned but not yet written. |
| ⛔ | Block is not applicable to Herold (rationale given below). |

---

## Building Block Index

### 1. Project Foundations

| Block | Title | Status | File |
|-------|-------|--------|------|
| P1 | Goals and Constraints | ✅ | [`P1-ziele-rahmenbedingungen.md`](P1-ziele-rahmenbedingungen.md) (annex: [`P1-constraints.md`](P1-constraints.md)) |
| P2 | Architecture Overview | ✅ | [`P2-architekturueberblick.md`](P2-architekturueberblick.md) |

### 2. Processes and Functions

| Block | Title | Status | File |
|-------|-------|--------|------|
| F1 | Business Processes | ✅ | [`F1-geschaeftsprozesse.md`](F1-geschaeftsprozesse.md) |
| F2 | Use Cases | ✅ | [`F2-anwendungsfaelle.md`](F2-anwendungsfaelle.md) |
| F3 | Application Functions | ✅ | [`F3-anwendungsfunktionen.md`](F3-anwendungsfunktionen.md) |

### 3. Data

| Block | Title | Status | File |
|-------|-------|--------|------|
| D1 | Data Model | 🛠 | `D1-datenmodell.md` |
| D2 | Data Type Catalogue | 🛠 | `D2-datentypen.md` |

### 4. User Interface

| Block | Title | Status | File |
|-------|-------|--------|------|
| B1 | Dialogue Specification | 🛠 | `B1-dialogspezifikation.md` |
| B2 | Batch | ⛔ | — |
| B3 | Print Output | ⛔ | — |

### 5. Interfaces to Legacy and Neighbouring Systems

| Block | Title | Status | File |
|-------|-------|--------|------|
| S1 | Neighbouring System Interfaces | 🛠 | `S1-nachbarsysteme.md` |
| S2 | Data Migration | ⛔ | — |
| S3 | Commissioning / Rollout | 🛠 | `S3-inbetriebnahme.md` |

### 6. Cross-cutting Aspects

| Block | Title | Status | File |
|-------|-------|--------|------|
| N1 | Non-functional Requirements | ✅ | [`N1-nichtfunktional.md`](N1-nichtfunktional.md) |
| N2 | Cross-cutting Concepts | 🛠 | `N2-querschnittskonzepte.md` |

### 7. Supplementary Blocks

| Block | Title | Status | File |
|-------|-------|--------|------|
| E1 | Reading Guide | ✅ | this document (section above) |
| E2 | Glossary | ✅ | [`E2-glossar.md`](E2-glossar.md) |

---

## Blocks Not Applicable

The following blocks of the Siedersleben model are deliberately not produced for Herold. The rationale is recorded here so that the absence is intentional and documented.

### B2 — Batch

Herold has no batch processing. The processing pipeline is synchronous inside the HTTP request (see ADR-002). The production environment forbids `crontab`, and there is no queue, worker, or scheduler.

### B3 — Print Output

Herold has no reports, PDFs, or other print artefacts. The sole output is a GitHub Issue, which is covered by S1.

### S2 — Data Migration

Herold is a greenfield project with no predecessor system and no legacy data to migrate. Initial deployment starts with an empty database.
