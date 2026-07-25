# Herold — Documentation

This directory holds the project documentation for **Herold**, a voice-based task dispatcher for local AI agents (Laravel 13 + Inertia.js + Vue 3). The documentation is split into two layers:

| Directory | Layer | Structure | Purpose |
|-----------|-------|-----------|---------|
| [`spec/`](spec/) | Specification — *what* and *why* | [Siedersleben building blocks](spec/SIEDERSLEBEN.md) | Implementation-free description of goals, processes, data, UI, neighbouring systems, and cross-cutting requirements. |
| [`arch/`](arch/) | Architecture — *how* | [arc42](https://arc42.org/) + ADRs | Solution strategy, components, runtime view, deployment, and architecture decisions. |

The split is intentional and binding: code-level detail (file paths, class names, library APIs, codecs, SQL) **does not** belong in `spec/`. It lives in `arch/` (ADRs) or in the code itself. See [`spec/README.md`](spec/README.md) for the rationale and the block index, and [`arch/A09-architecture-decisions.md`](arch/A09-architecture-decisions.md) for the running list of decisions.

Diagrams are versioned as PlantUML sources next to the documents (`*/diagrams/`), with rendered PNGs in the sibling `*/diagrams-png/` directories. Regenerate them with [`scripts/generate-diagrams.sh`](../scripts/generate-diagrams.sh).

---

## Use as a worked example for WK_1106

This documentation also serves as a **running real-world example** for the lecture **WK_1106 — Wirtschaftsinformatik-Projekt I (Softwaretechnik)** at THM (B.Sc. Wirtschaftsinformatik, Prof. Dr. Carsten Lucke). Course repository: <https://github.com/carstenlucke/thm_wkb_wk-1106>.

It illustrates the documentation style, granularity, and traceability expected from student project teams:

- **Specification after Siedersleben** — see [`spec/`](spec/) and the block index in [`spec/README.md`](spec/README.md). Blocks that do not apply (e.g. *B2 Batch*, *S2 Data Migration*) are explicitly marked as such with a short rationale rather than silently omitted.
- **Architecture after arc42 + ADRs** — see [`arch/`](arch/), with decisions captured in [`arch/A09-architecture-decisions.md`](arch/A09-architecture-decisions.md). Each ADR records context, alternatives, decision, rationale, and consequences.
- **Spec ↔ architecture ↔ code traceability** — use cases from `spec/F2-anwendungsfaelle.md` are realised in the architecture as **interactions across components** (runtime / sequence view), not as a 1:1 mapping to a single component. The **logical components** of the architecture are in turn recognisable in the source code and directory structure. Identifiers (use case IDs, data type names from `spec/D2-datentypen.md`) are kept consistent across all three layers.
- **Diagrams as versioned sources**, not as opaque images.

### Disclaimer

Herold is an **ongoing project** and not finished. The documents evolve, and they are not a flawless template in every detail. They show the **style, level of detail, and the linking of spec, architecture, ADRs and code** that the lecture expects — use them as inspiration, not as something to copy verbatim.

For the formal lecture requirements (milestones, deliverables, grading), refer to the [WK_1106 course repository](https://github.com/carstenlucke/thm_wkb_wk-1106) and to the course materials in Moodle.
