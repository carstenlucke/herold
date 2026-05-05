# Architecture Documentation — TODO

Open items to address when the architecture documentation is written out
in full.

## Open items

- **Anchor `relational-datamodel.plantuml` in the arch docs.** The
  diagram lives at `docs/arch/diagrams/relational-datamodel.plantuml`
  but nothing currently references it. When the arch docs are fleshed
  out, give the diagram a textual home — either a dedicated companion
  document (e.g. `RELATIONAL_DATAMODEL.md`) that embeds the PNG,
  documents the table-to-D1 mappings, the `metadata` JSON shape per
  message type, the users-singleton trigger, and the framework-column
  rationale, or fold this material into a broader persistence section.
  Cross-link from `docs/spec/D1-datenmodell.md` (the "physical schema
  lives in the architecture layer" pointer and the *Out of Scope*
  section) so the path from domain model to concrete schema is
  navigable.

- **Technical concept for per-`MessageTypeDT` configuration in
  `config/herold.php`.** D2.5 fixes the `MessageTypeDT` enumeration
  (`general`, `youtube`, `diary`, `obsidian`, `todo`); the per-value
  prompt, GitHub label, and extra-field shape live in
  `config/herold.php` as host configuration. Document the concrete
  shape of that configuration: the array structure per message type,
  how the prompt template / GitHub label / extra-field declarations are
  expressed, how `FieldDT` values map to PHP/validation, how the
  `metadata` JSON shape is derived from the configured slots, and how
  AF-04 / AF-08 consume the configuration. Should also describe how
  the spec-level enum is enforced in code (e.g. a PHP enum or
  validation guard) so config keys cannot drift away from the spec
  set.
