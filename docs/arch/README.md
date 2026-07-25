# Herold — Architecture (arc42)

Architecture documentation of Herold, structured according to the **arc42** template (version 9.0, July 2025). Each chapter lives in its own file; this document is the orchestrator: it introduces the structure and indexes all chapters.

Reference template: <https://arc42.org/>. A local Markdown copy of the template is checked out under [`arc42-template-EN-withhelp-gitHubMarkdown/`](arc42-template-EN-withhelp-gitHubMarkdown/) (gitignored). Background reading: Hruschka & Starke, *SW-Arch kompakt* (local PDF in this directory).

> Note on chapter mapping: in arc42 9.0, the older chapters *Technical Concepts* and *Typical Structures, Patterns and Workflows* from earlier editions are merged into **chapter 8 — Cross-cutting Concepts**.

---

## Conventions

- One file per arc42 chapter, named `A<NN>-<title>.md` (`A01` … `A12`). The `A` prefix marks the file as belonging to the architecture documentation, parallel to the block codes in [`../spec/`](../spec/) (`P1`, `F2`, …).
- Headings inside the files use plain arc42 numbering (`# 1`, `## 1.1`, …), not the file prefix.
- Language: English. Implementation-level detail (file paths, class names, library APIs, SQL, codecs) belongs here, in contrast to [`../spec/`](../spec/), which is implementation-free.

## Status legend

| Symbol | Meaning |
|--------|---------|
| 🛠 | Skeleton — headings only, no content yet. |
| 🟡 | Partial — content imported or in progress. |
| ✅ | Drafted. |

---

## Chapter Index

| # | Title | Status | File |
|---|-------|--------|------|
| 1 | Introduction and Goals | ✅ | [`A01-introduction-and-goals.md`](A01-introduction-and-goals.md) |
| 2 | Architecture Constraints | ✅ | [`A02-architecture-constraints.md`](A02-architecture-constraints.md) |
| 3 | Context and Scope | ✅ | [`A03-context-and-scope.md`](A03-context-and-scope.md) |
| 4 | Solution Strategy | ✅ | [`A04-solution-strategy.md`](A04-solution-strategy.md) |
| 5 | Building Block View | ✅ | [`A05-building-block-view.md`](A05-building-block-view.md) |
| 6 | Runtime View | ✅ | [`A06-runtime-view.md`](A06-runtime-view.md) |
| 7 | Deployment View | ✅ | [`A07-deployment-view.md`](A07-deployment-view.md) |
| 8 | Cross-cutting Concepts | ✅ | [`A08-cross-cutting-concepts.md`](A08-cross-cutting-concepts.md) |
| 9 | Architecture Decisions | ✅ | [`A09-architecture-decisions.md`](A09-architecture-decisions.md) |
| 10 | Quality Requirements | ✅ | [`A10-quality-requirements.md`](A10-quality-requirements.md) |
| 11 | Risks and Technical Debts | ✅ | [`A11-risks-and-technical-debts.md`](A11-risks-and-technical-debts.md) |
| 12 | Glossary | ✅ | [`A12-glossary.md`](A12-glossary.md) |
