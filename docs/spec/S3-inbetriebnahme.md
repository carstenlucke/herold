# S3 — Commissioning / Rollout

S3 describes how Herold is brought into productive operation and how subsequent releases reach the same environment, in the sense of Siedersleben (chapter 4.6): the *Inbetriebnahme* block names the preconditions on the host, the persistent surfaces that must survive every release, the sequence of activities at first commissioning, and the (lighter) sequence for ongoing releases. Its purpose at spec level is to give an operating IT function — the party that controls the host — a clear picture of what Herold expects from the environment and what is left for the deployer to do.

S3 deliberately stays at the level of *what* must happen and *which constraints* govern it. *How* deployment is carried out concretely — file paths, build pipeline, FTP tooling, server software configuration — belongs in the architecture-level deployment view (arc42 *Verteilungssicht* in [`docs/arch/`](../arch/)) and in the deployment scripts themselves. S3 is **not** a runbook.

S3 is shaped by Herold's particular situation: greenfield system, single operator, shared hosting, no scheduler, no queue. Several aspects that the Siedersleben *Inbetriebnahme* block addresses for classical replacement projects are therefore not applicable here and are listed explicitly in [S3.7](#s37-out-of-scope-for-s3).

---

## S3.1 Conventions

The following conventions apply to every activity described in this block.

- **No predecessor system.** Herold is greenfield ([P2.2](P2-architekturueberblick.md#p22-neighbouring-systems)). There is no legacy system to replace, no parallel-run period, no cut-over date. Initial commissioning starts with an empty database; data migration ([S2](README.md#s2--data-migration)) is not applicable.
- **Single tenant, single operator.** A Herold installation serves exactly one operator ([CON-3a-04](P1-constraints.md#con-3a-04-single-user-system)). Rollout is per-installation, not per-tenant; multi-installation rollouts and tenant onboarding are out of scope ([N2.9](N2-querschnittskonzepte.md#n29-out-of-scope-for-n2)).
- **No scheduler in the runtime path.** All processing runs synchronously inside an HTTP request ([NFR-12a-01](N1-nichtfunktional.md#12a-speed-and-latency-requirements)). No commissioning step depends on cron, queue workers, or background services — neither in the runtime path nor in the rollout itself ([CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production)).
- **Out-of-band channels.** Two host-side channels are required by the application beyond the public web edge: a write channel into the host file store (used both for deploying release artefacts and for the recovery flow [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)), and an optional shell channel for one-off administrative actions. Their concrete realisation is a host concern; S3 only requires that they exist.

---

## S3.2 Host Preconditions

The host must satisfy the following before Herold can be commissioned. Each entry is a precondition the IT function controls; concrete versions and switches live in [`P1-constraints.md`](P1-constraints.md) and in the architecture-level deployment view.

| Aspect | Requirement | Source |
|--------|-------------|--------|
| **Application runtime** | A runtime matching the language and framework declared by [CON-3a-01](P1-constraints.md#con-3a-01-laravel-13-monolith) is available on the host. The runtime is invoked per HTTP request; no long-running application process is expected. | [CON-3a-01](P1-constraints.md#con-3a-01-laravel-13-monolith) |
| **Persistent storage** | A writable area on the host file store that is *not* overwritten by deployments. It holds the SQLite database file ([CON-3a-03](P1-constraints.md#con-3a-03-sqlite-database)), the captured audio documents referenced by `VoiceNote.audioPath` ([D1.1](D1-datenmodell.md#voicenote)), the application log ([N2.6](N2-querschnittskonzepte.md#n26-logging)), and the recovery-token drop point used by [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access). | [CON-3a-03](P1-constraints.md#con-3a-03-sqlite-database), [NFR-14a-02](N1-nichtfunktional.md) |
| **Public web edge over HTTPS** | The application is reachable over HTTPS at a stable URL. HTTPS is required for in-browser audio capture to be available and for credential transport ([NFR-13a-01](N1-nichtfunktional.md#13a-expected-physical-environment), [NFR-15a-01](N1-nichtfunktional.md)); termination is the host's responsibility. | [NFR-13b-01](N1-nichtfunktional.md) |
| **Out-of-band write access to the host file store** | A write channel into the host file store independent of the public web edge — for placing release artefacts and for the recovery channel ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)). Its concrete protocol is a host decision. | [NFR-13b-01](N1-nichtfunktional.md), [NFR-14a-02](N1-nichtfunktional.md) |
| **Optional shell access** | A shell channel restricted to one-off administrative actions (initial schema bootstrap, post-release schema migrations). Not used in the request path; not required by any use case. | [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production) |
| **Outbound network reachability** | The host can reach the third-party APIs catalogued in [P2.2](P2-architekturueberblick.md#p22-neighbouring-systems) (NB-02 — NB-04) over HTTPS. Synchronous processing ([NFR-12a-01](N1-nichtfunktional.md#12a-speed-and-latency-requirements)) implies these calls are blocking; outbound egress must not be filtered in a way that breaks them. | [P2.2](P2-architekturueberblick.md#p22-neighbouring-systems), [S1.3](S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)–[S1.5](S1-nachbarsysteme.md#s15--nb-04--github-issues-api) |
| **No scheduler, no queue, no container runtime** | The host is *not* required to provide cron, a queue worker, or a container runtime, and the application must not depend on any of them ([CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production)). | [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production), [NG-04](P1-ziele-rahmenbedingungen.md) |

If any precondition is not satisfied, commissioning cannot complete; the gap is a host-side change, not an application-side workaround.

---

## S3.3 Persistent State Surfaces

The state described below is established once at first commissioning and must survive every subsequent release. A deployment that overwrites any of these surfaces destroys operator data or operator-controlled configuration and is therefore a defect in the deployment procedure, not an expected outcome.

| Surface | Owner | Lifetime | Notes |
|---------|-------|----------|-------|
| **Application database** | Operator | From first commissioning onwards. | Single SQLite file ([CON-3a-03](P1-constraints.md#con-3a-03-sqlite-database)). Holds the entities of [D1](D1-datenmodell.md). Initialised empty at first commissioning; never reset by a deployment. |
| **Audio documents** | Operator | Until [UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note) deletes the owning `VoiceNote`. | Files referenced by `VoiceNote.audioPath` ([D1.1](D1-datenmodell.md#voicenote)). |
| **Application log** | Operator | Append-only between deployments. | Operational and security events per [N2.6](N2-querschnittskonzepte.md#n26-logging). Redaction rules of [NFR-15b-03](N1-nichtfunktional.md) apply unconditionally. |
| **Host configuration** | Host operator | Provisioned at first commissioning; updated out-of-band thereafter. | Carries third-party credentials ([N2.8](N2-querschnittskonzepte.md#n28-secret-handling)), the framework signing key, the per-`MessageTypeDT` host-config bindings ([NFR-14a-01](N1-nichtfunktional.md)), and the AI provider selection ([NFR-14c-01](N1-nichtfunktional.md)). Never observable from the browser ([NFR-15b-01](N1-nichtfunktional.md), [NFR-15b-02](N1-nichtfunktional.md)). |
| **Recovery drop point** | Operator (out-of-band) | Transient; populated only during a recovery attempt. | The host file-store location into which the operator places `RecoveryToken.token` for [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access). Lifetime governed by [NFR-15a-04](N1-nichtfunktional.md). |

The deployment procedure must therefore distinguish *application artefacts* (replaced wholesale on every release) from *persistent state surfaces* (touched by no release).

---

## S3.4 First Commissioning

The activities below are performed once, in order, when Herold is brought into operation on a host. Each activity is scoped at spec level; concrete commands and tooling are out of scope.

1. **Verify host preconditions.** All entries of [S3.2](#s32-host-preconditions) are confirmed before any application artefact is uploaded. A missing precondition aborts commissioning.
2. **Provision host configuration.** The host operator places the host-side configuration described in [S3.3](#s33-persistent-state-surfaces) — third-party credentials, framework signing key, per-`MessageTypeDT` bindings ([NFR-14a-01](N1-nichtfunktional.md)), AI provider selection ([NFR-14c-01](N1-nichtfunktional.md)). Configuration is opaque to the application until it is read at request time; misconfiguration surfaces as a runtime failure on the affected use case.
3. **Place the first release artefact.** The build output of the chosen release is transferred onto the host through the out-of-band channel of [S3.2](#s32-host-preconditions). The artefact contains application code only; no persistent state.
4. **Initialise the empty database.** The SQLite file is created and the schema is established through the optional shell channel of [S3.2](#s32-host-preconditions). After this step the application's persistent surfaces of [S3.3](#s33-persistent-state-surfaces) exist and are empty (no `VoiceNote`, no `Operator`, no `RecoveryToken`).
5. **Establish the single operator.** The initial `Operator` row ([D1.1](D1-datenmodell.md#operator)) — carrying the hash of the operator's API key — is seeded out-of-band through the optional shell channel of [S3.2](#s32-host-preconditions); the API key itself is delivered to the operator on the same out-of-band channel and never travels through the public web edge. The operator then runs [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) for the first time, which includes [UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor) inline to bind the second factor. From this point on the authentication gate of [N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session) is active for every other use case.
6. **Smoke-test the request path.** A complete pass through the pipeline ([F1](F1-geschaeftsprozesse.md): record → process → review → send) confirms outbound reachability of NB-02 — NB-04 ([P2.2](P2-architekturueberblick.md#p22-neighbouring-systems)), in-browser audio capture ([NFR-13a-01](N1-nichtfunktional.md#13a-expected-physical-environment)), and the persistence surfaces of [S3.3](#s33-persistent-state-surfaces). If the smoke test fails, the cause is treated as a precondition gap (S3.2) or a configuration gap (step 2) before any further releases are attempted.

The order matters. Steps 4 and 5 cannot be reversed: there is no operator before the schema exists, and the gate of [N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session) cannot be passed before an operator exists. Steps 1–3 are commutative among themselves but must all complete before step 4.

---

## S3.5 Ongoing Releases

A release after first commissioning is the lightweight case: the persistent surfaces of [S3.3](#s33-persistent-state-surfaces) already exist, the operator already exists, host configuration is already in place. A release replaces application artefacts; it does not re-initialise state.

The activities are:

1. **Off-host build.** The release artefact is produced off the host so that the host does not need build tooling ([CON-3a-05](P1-constraints.md#con-3a-05-vite-8-build-toolchain)). The artefact is a deterministic function of the released source revision.
2. **Artefact upload.** The artefact replaces the application files on the host through the out-of-band channel of [S3.2](#s32-host-preconditions). Persistent state surfaces ([S3.3](#s33-persistent-state-surfaces)) are *not* part of the artefact and are *not* affected by the upload.
3. **Schema migration (only if the release contains one).** If the release introduces a schema change, the migration is run once through the optional shell channel of [S3.2](#s32-host-preconditions). Schema migrations are forward-only.
4. **Post-release verification.** A short pass through [F1](F1-geschaeftsprozesse.md) confirms that the pipeline still completes end-to-end. Operator action is not otherwise required: an active session continues to satisfy the gate of [N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session) across the upload, modulo the framework signing key remaining stable across releases.

A release that contains no schema change requires no use of the shell channel; the artefact upload is the entire release activity.

---

## S3.6 Rollback and Point of No Return

S3 follows the Siedersleben *Inbetriebnahme* convention of naming a point of no return explicitly:

- **Schema-free releases are reversible.** Re-uploading the previous release artefact restores the application to the prior code revision; persistent state surfaces ([S3.3](#s33-persistent-state-surfaces)) are unaffected by either direction of the swap.
- **Schema migrations are the point of no return.** Once step 3 of [S3.5](#s35-ongoing-releases) has been executed, the database schema has moved forward. Restoring the prior code revision against the migrated database is *not* supported; restoring the database to its pre-migration state requires a backup taken before the migration. The deployment procedure must therefore treat the moment immediately before a schema migration as a snapshot point.
- **Recovery is not deployment.** The recovery flow ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)) is the operator's path back into a locked installation; it is not a means to undo a release. Recovery replaces operator credentials only and never alters application code or persistent operator data ([N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session)).

Backup procedure, snapshot mechanics, and concrete restore steps are deployment concerns and live in the architecture-level deployment view, not in S3.

---

## S3.7 Out of Scope for S3

- **Concrete deployment tooling.** The choice of file-transfer client, the layout of the release artefact, and the workflow that produces the artefact are deployment-procedure concerns and live in [`docs/arch/`](../arch/) and in the deployment scripts.
- **Server software configuration.** Web-server configuration (rewriting, document root, TLS), runtime extension lists, and host-level filesystem layout are host-side decisions; S3 only declares the preconditions ([S3.2](#s32-host-preconditions)) they must collectively satisfy.
- **Backup and restore mechanics.** The fact that schema migrations create a point of no return ([S3.6](#s36-rollback-and-point-of-no-return)) is a spec-level statement; backup format, schedule, and restore procedure are deployment concerns.
- **Multi-environment rollout.** Staging or canary environments are not modelled at spec level; a single installation per operator ([CON-3a-04](P1-constraints.md#con-3a-04-single-user-system)) is the unit of rollout.
- **Data migration from a predecessor system.** Not applicable ([S2](README.md#s2--data-migration), greenfield).
- **Operator onboarding beyond bootstrap.** [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) and [UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor) are use cases in [F2](F2-anwendungsfaelle.md), not commissioning activities; S3 references them for ordering but does not redefine them.

---

## S3.8 Cross-references

| Block | Relevance to S3 |
|-------|-----------------|
| [P1](P1-ziele-rahmenbedingungen.md) | [CON-3a-01](P1-constraints.md#con-3a-01-laravel-13-monolith), [CON-3a-03](P1-constraints.md#con-3a-03-sqlite-database), [CON-3a-04](P1-constraints.md#con-3a-04-single-user-system), [CON-3a-05](P1-constraints.md#con-3a-05-vite-8-build-toolchain), [CON-3b-01](P1-constraints.md#con-3b-01-shared-hosting-production) bound the host preconditions of [S3.2](#s32-host-preconditions); [NG-04](P1-ziele-rahmenbedingungen.md) excludes scheduler-driven commissioning steps. |
| [P2](P2-architekturueberblick.md) | [P2.2](P2-architekturueberblick.md#p22-neighbouring-systems) declares the outbound neighbours whose reachability [S3.2](#s32-host-preconditions) requires and [S3.4](#s34-first-commissioning) step 6 verifies. |
| [F1](F1-geschaeftsprozesse.md) | The smoke test in [S3.4](#s34-first-commissioning) and the post-release verification in [S3.5](#s35-ongoing-releases) exercise the pipeline of F1 end-to-end. |
| [F2](F2-anwendungsfaelle.md) | The first run of [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) (with [UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor) inline) closes the operator-establishment step of first commissioning; [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) drives the recovery drop point listed in [S3.3](#s33-persistent-state-surfaces). |
| [D1](D1-datenmodell.md) | The persistent surfaces of [S3.3](#s33-persistent-state-surfaces) hold instances of D1 entities; the empty initial state of [S3.4](#s34-first-commissioning) step 4 is the absence of any D1 instance. |
| [N1](N1-nichtfunktional.md) | [NFR-12a-01](N1-nichtfunktional.md#12a-speed-and-latency-requirements), [NFR-13a-01](N1-nichtfunktional.md#13a-expected-physical-environment), [NFR-13b-01](N1-nichtfunktional.md), [NFR-14a-01](N1-nichtfunktional.md), [NFR-14a-02](N1-nichtfunktional.md), [NFR-14c-01](N1-nichtfunktional.md), [NFR-15a-01](N1-nichtfunktional.md), [NFR-15a-04](N1-nichtfunktional.md), [NFR-15b-01](N1-nichtfunktional.md), [NFR-15b-02](N1-nichtfunktional.md), [NFR-15b-03](N1-nichtfunktional.md) are the binding requirements that the preconditions and activities above implement. |
| [N2](N2-querschnittskonzepte.md) | [N2.4](N2-querschnittskonzepte.md#n24-authentication-and-session) governs the authentication gate that becomes active at [S3.4](#s34-first-commissioning) step 5; [N2.6](N2-querschnittskonzepte.md#n26-logging) governs the application-log surface listed in [S3.3](#s33-persistent-state-surfaces); [N2.8](N2-querschnittskonzepte.md#n28-secret-handling) governs the host-configuration credentials carried into every outbound call. |
| [`docs/arch/`](../arch/) | The arc42 *Verteilungssicht* (deployment view) is the authoritative location for the concrete deployment procedure, file layout, web-server configuration, build pipeline, and backup/restore mechanics deliberately excluded from S3. |
