# Architecture Documentation — TODO

Open items to address when the architecture documentation is revised.

## Open items

- **Cross-link `docs/spec/D1-datenmodell.md` to the persistence concept.**
  D1's "physical schema lives in the architecture layer" pointer and its
  *Out of Scope* section should link concretely to
  [`A08-cross-cutting-concepts.md § 8.1`](A08-cross-cutting-concepts.md#81-domain-model-and-persistence),
  so the path from domain model to concrete schema is navigable from the
  spec side. (Spec-side edit; deliberately not done as part of the arch
  chapter fill.)

## Resolved

- ~~Anchor `relational-datamodel.plantuml` in the arch docs.~~ Done:
  embedded and explained (table-to-D1 mapping, `metadata` JSON shape,
  users-singleton trigger, framework-column rationale) in
  [`A08 § 8.1`](A08-cross-cutting-concepts.md#81-domain-model-and-persistence).
- ~~Technical concept for per-`MessageTypeDT` configuration in
  `config/herold.php`.~~ Done: config shape, consumers, slot-type → PHP
  validation mapping, snake_case storage keys, and the enforcement of the
  spec-level catalogue are documented in
  [`A08 § 8.2`](A08-cross-cutting-concepts.md#82-type-driven-configuration)
  (layering note records that the catalogue is realised as config keys,
  not a PHP enum) and [`A08 § 8.3`](A08-cross-cutting-concepts.md#83-validation).
