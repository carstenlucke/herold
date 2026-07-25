# 5 Building Block View

The building block view refines the system top-down, alternating black boxes and white boxes per refinement level (Starke/Hruschka, *SW-Arch kompakt*, ch. 5):

- **Level 0** is the context view of [chapter 3](A03-context-and-scope.md) — Herold as one black box among its neighbours.
- **Level 1** ([§ 5.1](#51-whitebox-overall-system)) opens that black box: the whitebox *Herold* and, as [§ 5.1.1](#511-blackbox-ui-shell)–[§ 5.1.6](#516-blackbox-log-redaction), the black-box descriptions of its six contained building blocks.
- **Level 2** ([§ 5.2](#52-level-2)) opens four of those six blocks as whiteboxes, each with its own diagram; the contained elements appear as black boxes.
- **Level 3** ([§ 5.3](#53-level-3)) opens one level-2 black box — `AIService` — whose inner seam structure carries an architectural decision of its own.

Every block maps to a concrete directory, class, file, or method in the source tree; nothing is aspirational. Refinement is deliberately asymmetric: a branch ends as soon as further decomposition would only restate code (justifications at the end of each level).

**Description templates.** Blocks are described with the templates of Starke/Hruschka (*SW-Arch kompakt*, p. 35 blackbox, p. 39 whitebox), which are the richer variant of the fields [arc42 v8](https://docs.arc42.org/section-5/) asks for here:

| Template | Rows |
|----------|------|
| **Blackbox** (§§ 5.1.1–5.1.6) | Purpose/Responsibility · Interface(s) provided · Interface(s) required · Quality/performance · Dependencies · Code artefacts · Fulfilled requirements · Variability · Tests · Open issues · Refined in |
| **Whitebox** (§ 5.1, §§ 5.2.1–5.2.4, § 5.3.1) | Whitebox of · Overview diagram · Contained building blocks · Local relationships · Design decisions · Rejected alternatives · References · Open issues |

Three deviations, all deliberate: the two list-valued whitebox rows are rendered as separate tables below the template table; the local black boxes of levels 2 and 3 use the tabular short form both sources permit, because a full template per file would only restate code; *Refined in* is added as a navigation row. Global decisions stay in [chapter 9](A09-architecture-decisions.md) and are referenced, not repeated (book, p. 40).

The figure below shows the complete decomposition at a glance — the refinement schema of the book applied to Herold. Grey folders are black boxes, framed packages are the whiteboxes that open them, and each dashed *refined* edge connects a black box on level *n* to its whitebox on level *n + 1*:

![Building Block View — decomposition overview](diagrams-png/a05-decomposition-overview.png)

*Source: [`diagrams/a05-decomposition-overview.plantuml`](diagrams/a05-decomposition-overview.plantuml). This overview shows structure only; the relations inside each whitebox are drawn in the per-whitebox diagrams of [§ 5.1](#51-whitebox-overall-system)–[§ 5.3](#53-level-3).*

---

## 5.1 Whitebox Overall System

![Building Block View — Level 1](diagrams-png/a05-building-blocks.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | **Herold** — the level-0 black box of [chapter 3](A03-context-and-scope.md). |
| **Overview diagram** | Figure above; source [`diagrams/a05-building-blocks.plantuml`](diagrams/a05-building-blocks.plantuml). |
| **Contained building blocks** | Six blocks — table below, described as black boxes in §§ 5.1.1–5.1.6. |
| **Local relationships** | The cross-block interfaces — table below. |
| **Design decisions** | One Laravel monolith, one deployable ([ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui), [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing)). The cut lines inside it follow the four-layer strategy of [§ 4.2](A04-solution-strategy.md#42-top-level-decomposition) and are chosen along the two portability seams demanded by [QG-04](A01-introduction-and-goals.md#12-quality-goals) (AI provider) and [TECH-05](A02-architecture-constraints.md#21-technical-constraints) (GitHub), and along the Inertia bridge that separates browser-executed from server-executed code without introducing an API tier. |
| **Rejected alternatives** | SPA against a separate JSON API tier, Blade + Alpine ([ADR-001](A09-architecture-decisions.md#adr-001-inertiajs-as-frontend-bridge-no-separate-api-layer-for-the-browser-ui)); queue/cron-based processing ([ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing)); local ticket store plus agent memory ([ADR-003](A09-architecture-decisions.md#adr-003-github-issues-as-sole-ticket-store)). |
| **References** | Runtime: [chapter 6](A06-runtime-view.md). Deployment: [chapter 7](A07-deployment-view.md). Cross-cutting rules: [chapter 8](A08-cross-cutting-concepts.md). |
| **Open issues** | `Middleware/VerifyApiKey` belongs to no block's behaviour — [D-06](A11-risks-and-technical-debts.md#112-technical-debts). |

**Contained building blocks** (black boxes at this level; described in §§ 5.1.1–5.1.6):

| # | Block | Code artefacts | Responsibility (one line) |
|---|-------|----------------|---------------------------|
| [5.1.1](#511-blackbox-ui-shell) | **UI Shell** | `resources/js/` | Everything executed in the operator's browser. |
| [5.1.2](#512-blackbox-web-edge) | **Web Edge** | `routes/web.php`, `app/Http/` | HTTP boundary, auth gate, validation — and the pipeline orchestration. |
| [5.1.3](#513-blackbox-domain-services) | **Domain Services** | `app/Services/` | Herold-owned logic between edge and adapters. |
| [5.1.4](#514-blackbox-integration-adapters) | **Integration Adapters** | `app/Services/` | The only code knowing external endpoints and credentials. |
| [5.1.5](#515-blackbox-persistence) | **Persistence** | `app/Models/`, `app/Enums/`, `storage/` | Eloquent models, SQLite schema, audio file store. |
| [5.1.6](#516-blackbox-log-redaction) | **Log Redaction** | `app/Logging/` | Secret masking on every log channel. |

**Local relationships** (cross-block interfaces; each is detailed in the whitebox that owns it):

| Interface | Between | Contract |
|-----------|---------|----------|
| Inertia page protocol | UI Shell ↔ Web Edge | Server-driven page props over HTTPS; no separate JSON API for the browser (ADR-001). Two deliberate exceptions: `GET /types` (prompt-stripped type catalogue as JSON) and `GET /notes/{note}/audio` (audio streaming). |
| Audio upload | UI Shell → Web Edge | `POST /notes` as `multipart/form-data` (`audio` blob + `type` + `metadata`), validated by `StoreVoiceNoteRequest` ([§ 8.3](A08-cross-cutting-concepts.md#83-validation)). |
| AI seam | Web Edge / Domain Services → Integration Adapters | `transcribe(string $audioPath): string` and `chat(string $systemPrompt, string $userMessage, float $temperature): array` — the two operations behind which the AI provider is replaceable ([QG-04](A01-introduction-and-goals.md#12-quality-goals)); refined in [§ 5.3.1](#531-whitebox-aiservice). |
| Ticket seam | Web Edge → Integration Adapters | `createIssue(string $title, string $body, array $labels): array{number, html_url}` — the one-way push of [S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api). |
| Persistence access | Web Edge, Domain Services → Persistence | Eloquent model API and the `local` storage disk; no other block writes durable state. |
| Type catalogue | all server-side blocks → `config/herold.php` | Per-type bindings (label, icon, `github_label`, `extra_fields`, `preprocessing_prompt`); the single resolution point of [N2.2](../spec/N2-querschnittskonzepte.md#n22-type-driven-configuration), detailed in [§ 8.2](A08-cross-cutting-concepts.md#82-type-driven-configuration). |
| Log stream | all server-side blocks → Log Redaction | Not called but intercepted: every record on the `single`, `daily`, and `stderr` channels ([§ 8.7](A08-cross-cutting-concepts.md#87-logging)). |

The UI Shell is *served by* the deployable but *executes in* the browser; the deployment consequences of that split are covered in [chapter 7](A07-deployment-view.md). The runtime interplay of the blocks is traced per scenario in [chapter 6](A06-runtime-view.md).

### 5.1.1 Blackbox UI Shell

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | Renders the dialogues of [B1](../spec/B1-dialogspezifikation.md), captures audio in-browser, holds transient form state. No business rules, no secrets, no direct calls to external systems. |
| **Interface(s) provided** | The operator-facing UI. |
| **Interface(s) required** | The Inertia page protocol, the audio upload, and the two JSON/streaming endpoints of the Web Edge — nothing else; every server contact goes through the Web Edge. |
| **Quality/performance** | One codebase for desktop and mobile ([NFR-10a-01](../spec/N1-nichtfunktional.md#10a-appearance-requirements)); no client store — every mutation round-trips ([§ 8.10](A08-cross-cutting-concepts.md#810-ui-architecture)). |
| **Dependencies** | Browser with `MediaRecorder`/`getUserMedia` on an HTTPS origin ([NFR-13a-01](../spec/N1-nichtfunktional.md#13a-expected-physical-environment)); Vite/Node at build time only. |
| **Code artefacts** | `resources/js/` (built into `public/build/` at release time). |
| **Fulfilled requirements** | [NFR-10a-01](../spec/N1-nichtfunktional.md#10a-appearance-requirements), [NFR-11a-01](../spec/N1-nichtfunktional.md#11a-ease-of-use-requirements), [NFR-13a-01](../spec/N1-nichtfunktional.md#13a-expected-physical-environment). |
| **Variability** | New dialogue = one page plus its route; new message types need no UI change, because label, icon, and extra fields arrive from the type catalogue; two switchable Vuetify themes. |
| **Tests** | Component specs plus the frontend build as a CI gate ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | The codec preference chain is browser-dependent and only manually verified. |
| **Refined in** | [§ 5.2.1](#521-whitebox-ui-shell). |

### 5.1.2 Blackbox Web Edge

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | Terminates every HTTP request: route table, session auth gate, rate limits, form-request validation, security headers — and the **pipeline orchestration**: the `process`/`send` controller actions drive the synchronous pipeline of [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing). |
| **Interface(s) provided** | All HTTP routes: public auth/recovery routes; session-guarded application routes. |
| **Interface(s) required** | Method calls into Domain Services, Integration Adapters, and Persistence; the type catalogue. |
| **Quality/performance** | Owns the latency budget of [NFR-12a-01](../spec/N1-nichtfunktional.md#12a-speed-and-latency-requirements): `process`/`send` block for the provider calls, bounded by the host's execution limit ([R-02](A11-risks-and-technical-debts.md#111-risks)). |
| **Dependencies** | Laravel session, `auth` guard, and `throttle` infrastructure; middleware wiring in `bootstrap/app.php`. |
| **Code artefacts** | `routes/web.php`, `app/Http/`, wiring in `bootstrap/app.php`. |
| **Fulfilled requirements** | [NFR-15a-01](../spec/N1-nichtfunktional.md#15a-access-requirements)–[NFR-15a-04](../spec/N1-nichtfunktional.md#15a-access-requirements), [NFR-12a-01](../spec/N1-nichtfunktional.md#12a-speed-and-latency-requirements), [NFR-12d-01](../spec/N1-nichtfunktional.md#12d-reliability-and-availability-requirements). |
| **Variability** | A new use case is one controller action plus one route; rate limits are per-route parameters; security headers are set once for the whole `web` group. |
| **Tests** | The bulk of the acceptance suite runs through this block — auth, recovery, upload validation, headers, pipeline ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | Blocking long requests as an accepted ADR-002 consequence ([R-01](A11-risks-and-technical-debts.md#111-risks)); unwired `VerifyApiKey` ([D-06](A11-risks-and-technical-debts.md#112-technical-debts)); upload throttle deviates from spec ([D-07](A11-risks-and-technical-debts.md#112-technical-debts)(c)). |
| **Refined in** | [§ 5.2.2](#522-whitebox-web-edge). |

### 5.1.3 Blackbox Domain Services

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | Herold-owned logic that is neither HTTP handling nor external integration: prompt assembly and content post-processing, markdown sanitisation, message-type resolution. |
| **Interface(s) provided** | `process(VoiceNote)`, `sanitizeAndWrap(VoiceNote)`, `all()/get()/keys()`. |
| **Interface(s) required** | The generation seam of the Integration Adapters (`chat()`), Persistence (Eloquent writes), and the type catalogue in `config/herold.php`. |
| **Quality/performance** | Pure in-process work; the seconds inside `process()` belong to the delegated `chat()` call. |
| **Dependencies** | The note's type must resolve in the catalogue (unknown type → exception); no dependency among the three services. |
| **Code artefacts** | `app/Services/` (`PreprocessingService`, `IssueContentSanitizer`, `MessageTypeRegistry`). |
| **Fulfilled requirements** | [NFR-14a-01](../spec/N1-nichtfunktional.md#14a-maintenance-requirements), [NFR-15b-02](../spec/N1-nichtfunktional.md#15b-integrity-requirements), [NFR-15b-04](../spec/N1-nichtfunktional.md#15b-integrity-requirements). |
| **Variability** | Prompts, extra-field schemas, and labels are configuration, not code ([§ 8.2](A08-cross-cutting-concepts.md#82-type-driven-configuration)); sanitisation rules sit in one class ([§ 8.5](A08-cross-cutting-concepts.md#85-content-sanitisation)). |
| **Tests** | Acceptance coverage for the pipeline, sanitisation (incl. injection cases), and catalogue-driven extensibility ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | Sanitisation runs only at the dispatch boundary, not at the process/edit boundaries as specified — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(b). |
| **Refined in** | [§ 5.2.3](#523-whitebox-domain-services). |

### 5.1.4 Blackbox Integration Adapters

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | The only code that knows external endpoints, payload shapes, and credentials — one logical seam per neighbour: transcription (NB-02), generation (NB-03), ticket dispatch (NB-04). Both adapter classes use Laravel's `Http` facade; no vendor SDK. |
| **Interface(s) provided** | The AI seam and the ticket seam (see the interface table above). |
| **Interface(s) required** | Credentials and repository coordinates from `config/herold.php`; HTTPS egress to `api.openai.com` and `api.github.com`. |
| **Quality/performance** | Timeouts are the only guardrail (120 s transcription, 30 s GitHub); availability is the providers' ([R-01](A11-risks-and-technical-debts.md#111-risks)); a missing credential fails at construction. |
| **Dependencies** | Reachability of both neighbours; outbound HTTPS; secrets present in the environment ([chapter 7](A07-deployment-view.md)). |
| **Code artefacts** | `app/Services/` (`AIService`, `GitHubService`). |
| **Fulfilled requirements** | [NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements), [NFR-15b-01](../spec/N1-nichtfunktional.md#15b-integrity-requirements); realises [S1.3](../spec/S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)–[S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api). |
| **Variability** | A provider swap replaces an adapter body behind unchanged signatures ([QG-04](A01-introduction-and-goals.md#12-quality-goals)); credentials, base URL, and repository target are configuration — model identifiers are not. |
| **Tests** | Both seams are replaced by container-injected doubles in the acceptance suite, which doubles as proof of substitutability ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | Model identifiers hard-coded — [R-07](A11-risks-and-technical-debts.md#111-risks), [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(f); no automated test against the real providers — [D-03](A11-risks-and-technical-debts.md#112-technical-debts). |
| **Refined in** | [§ 5.2.4](#524-whitebox-integration-adapters), then [§ 5.3.1](#531-whitebox-aiservice). |

### 5.1.5 Blackbox Persistence

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | Owns all durable state: the `VoiceNote` and `User` Eloquent models, the `NoteStatus` enum, the SQLite schema (incl. the users-singleton trigger), and the private audio file store. |
| **Interface(s) provided** | Eloquent model API and `Storage` disk `local`. |
| **Interface(s) required** | None (leaf block). |
| **Quality/performance** | One SQLite file with single-writer semantics, sized for single-user load ([CON-3a-04](../spec/P1-constraints.md#con-3a-04-single-user-system)); audio stays outside the document root and is streamed, never served statically. |
| **Dependencies** | Writable database file and `storage/app/private/` on the host; migrations applied manually, never in the request path ([chapter 7](A07-deployment-view.md)). |
| **Code artefacts** | `app/Models/`, `app/Enums/`, `database/migrations/`, `storage/app/private/`. |
| **Fulfilled requirements** | [CON-3a-03](../spec/P1-constraints.md#con-3a-03-low-footprint-database), [CON-3a-04](../spec/P1-constraints.md#con-3a-04-single-user-system). |
| **Variability** | Schema evolves by migration only; the audio store is a filesystem disk, so relocating it is configuration; the singleton constraint lives in the schema and thus survives any new call path. |
| **Tests** | The DB-level singleton guarantee has its own acceptance test; every other test exercises the models against in-memory SQLite ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | Framework-only columns on `users` — [D-02](A11-risks-and-technical-debts.md#112-technical-debts); persisted transcript versus spec postcondition — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(d); hosting-side SQLite risk and missing backup — [R-03](A11-risks-and-technical-debts.md#111-risks), [D-04](A11-risks-and-technical-debts.md#112-technical-debts). |
| **Refined in** | Not refined further: its inner structure is a data model, not a component structure — documented as [§ 8.1](A08-cross-cutting-concepts.md#81-domain-model-and-persistence) with the [relational data model](diagrams-png/relational-datamodel.png). |

### 5.1.6 Blackbox Log Redaction

| Blackbox | Content |
|----------|---------|
| **Purpose/Responsibility** | Passive block: a Monolog tap that masks secrets in every log entry emitted by the other blocks, on the `single`, `daily`, and `stderr` channels. |
| **Interface(s) provided** | None callable — it intercepts. |
| **Interface(s) required** | The config keys whose values it masks. |
| **Quality/performance** | One pass per record, no I/O of its own; being a channel-level tap it cannot be bypassed by a forgetful caller — the property that makes it a block rather than a convention. |
| **Dependencies** | Channel configuration in `config/logging.php`. |
| **Code artefacts** | `app/Logging/` (`RedactSecrets`, `SecretRedactionProcessor`), wired in `config/logging.php`. |
| **Fulfilled requirements** | [NFR-15b-03](../spec/N1-nichtfunktional.md#15b-integrity-requirements). |
| **Variability** | Extending protection means one entry in the rule set; a new channel only needs the tap declared. |
| **Tests** | Own acceptance test asserting secrets never reach the log output ([§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept)). |
| **Open issues** | None. |
| **Refined in** | Not refined further: two classes with one rule set — documented as [§ 8.7](A08-cross-cutting-concepts.md#87-logging). |

---

## 5.2 Level 2

Level 2 opens the level-1 black boxes 5.1.1–5.1.4 as whiteboxes — one subsection, one diagram each. In the diagrams, grey boxes denote *other* level-1 blocks (context of the whitebox, not content). Persistence and Log Redaction are not refined (justification in §§ 5.1.5–5.1.6). Local black boxes use the tabular short form.

### 5.2.1 Whitebox UI Shell

![Level 2 — Whitebox UI Shell](diagrams-png/a05-l2-ui-shell.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | [§ 5.1.1 Blackbox UI Shell](#511-blackbox-ui-shell). |
| **Overview diagram** | Figure above; source [`diagrams/a05-l2-ui-shell.plantuml`](diagrams/a05-l2-ui-shell.plantuml). |
| **Contained building blocks** | Six local blocks — table below. |
| **Local relationships** | Table below; the only outbound relationships leave towards the Web Edge. |
| **Design decisions** | The decomposition mirrors the Vue/Inertia idiom: one page per dialogue of [B1](../spec/B1-dialogspezifikation.md), reusable components below, browser-API access isolated in composables — so the one platform-dependent piece (`MediaRecorder`) sits in a single composable. |
| **Rejected alternatives** | A client-side store (Pinia/Vuex) — state is server state ([§ 8.10](A08-cross-cutting-concepts.md#810-ui-architecture)); per-page recording code instead of one composable. |
| **References** | [B1](../spec/B1-dialogspezifikation.md), [`DESIGN.md`](../../DESIGN.md), [§ 8.10](A08-cross-cutting-concepts.md#810-ui-architecture). |
| **Open issues** | See § 5.1.1 (codec chain). |

Contained black boxes (paths relative to `resources/js/`):

| Block | Responsibility |
|-------|----------------|
| `app.ts` | Entry point: creates the Inertia app with an eager page resolver over `Pages/`, plus the Vuetify setup with the two custom themes (dark as default). |
| `Layouts/` (×1) | The single responsive chrome per [NFR-10a-01](../spec/N1-nichtfunktional.md#10a-appearance-requirements): navigation drawer on desktop, bottom navigation on mobile, the four top-level destinations, and sign-out. |
| `Pages/` (×10) | One page per dialogue of [B1](../spec/B1-dialogspezifikation.md): dashboard, note list and note detail (status stepper plus the Process/Send actions), recording, settings, and the authentication set (sign-in, TOTP enrolment and verification, recovery). |
| `Components/` (×5) | The reusable widgets the pages compose from — recorder UI, type selection, note card, status badge, transcript editor. No page-specific logic. |
| `Composables/` (×2) | The two pieces of stateful browser logic kept out of the components: audio capture (`MediaRecorder`, container-format preference chain, blob assembly) and triggering the two pipeline actions. |
| `Types/` | Shared TypeScript contracts mirroring the Inertia props; compile-time only, no runtime code. |

Local relationships:

| Relationship | Contract |
|--------------|----------|
| `app.ts` → `Layouts/` → `Pages/` | Page resolution and layout wrapping. |
| `Pages/` → `Components/` | Pages compose the widgets; the components' own nesting stays inside `Components/`. |
| `Components/`, `Pages/` → `Composables/` | Audio capture is consumed by the recorder component and its page; the pipeline actions by the note detail page. |
| `Pages/`, `Layouts/` → `Types/` | Compile-time prop contracts only. |
| `app.ts`, `Pages/` → Web Edge (outbound) | Inertia visits and form posts; the multipart audio upload; the two pipeline posts; audio streaming on the note detail page. |

### 5.2.2 Whitebox Web Edge

![Level 2 — Whitebox Web Edge](diagrams-png/a05-l2-web-edge.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | [§ 5.1.2 Blackbox Web Edge](#512-blackbox-web-edge). |
| **Overview diagram** | Figure above; source [`diagrams/a05-l2-web-edge.plantuml`](diagrams/a05-l2-web-edge.plantuml). |
| **Contained building blocks** | Four local blocks — table below. |
| **Local relationships** | Table below; outbound towards Domain Services, Integration Adapters, Persistence, and the type catalogue. |
| **Design decisions** | One controller per dialogue area; validation extracted where it is schema-driven (capture); everything auth-related concentrated in a single class so the gate has one owner. Security headers are middleware, not per-response code. |
| **Rejected alternatives** | Separate controllers per pipeline step — both actions operate on the same aggregate and share its guards; middleware-based API-key auth for the browser UI — the class exists but stays unwired. |
| **References** | [§ 8.4](A08-cross-cutting-concepts.md#84-authentication-and-session), [§ 8.3](A08-cross-cutting-concepts.md#83-validation), [F2](../spec/F2-anwendungsfaelle.md), [chapter 6](A06-runtime-view.md). |
| **Open issues** | `Middleware/VerifyApiKey` exists in the tree but is wired nowhere — [D-06](A11-risks-and-technical-debts.md#112-technical-debts). |

Contained black boxes (all under `app/Http/` unless noted):

| Block | Responsibility |
|-------|----------------|
| `routes/web.php` | The single route table: the public sign-in, TOTP, and recovery routes, everything else behind the session `auth` guard, and the per-route rate limits of [NFR-15a-02](../spec/N1-nichtfunktional.md#15a-access-requirements) ([§ 8.3](A08-cross-cutting-concepts.md#83-validation)). |
| `Controllers/` (×4) | One controller per dialogue area: authentication (both factors, enrolment, recovery — including the home-rolled RFC-6238 implementation of [TECH-13](A02-architecture-constraints.md#21-technical-constraints), [§ 8.4](A08-cross-cutting-concepts.md#84-authentication-and-session)); voice notes — the pipeline orchestrator of [UC-05](../spec/F2-anwendungsfaelle.md#uc-05--capture-voice-note)–[UC-11](../spec/F2-anwendungsfaelle.md#uc-11--delete-a-voice-note) with the two long-running synchronous actions of [ADR-002](A09-architecture-decisions.md#adr-002-devprod-parity----apache--synchronous-processing) ([chapter 6](A06-runtime-view.md)); dashboard ([UC-13](../spec/F2-anwendungsfaelle.md#uc-13--view-dashboard)); settings ([UC-12](../spec/F2-anwendungsfaelle.md#uc-12--view-settings)). |
| `Requests/` (×1) | Capture validation, derived from the type catalogue rather than hard-coded, plus the strip of undeclared metadata keys ([§ 8.3](A08-cross-cutting-concepts.md#83-validation)). |
| `Middleware/` (×2) | The response hardening headers on the whole `web` group ([§ 8.4](A08-cross-cutting-concepts.md#84-authentication-and-session)); the second class is unwired dead code (see *Open issues*). |

Local relationships:

| Relationship | Contract |
|--------------|----------|
| UI Shell → `routes/web.php` (inbound) | HTTPS: Inertia visits, form posts, multipart upload, audio streaming. |
| `routes/web.php` → `Controllers/` | One route group per controller: the authentication routes (throttled, public), the note routes (auth guard, capture throttled), the dashboard, and the settings view. |
| `Middleware/` ∙ `routes/web.php` | Not called but interposed: the header middleware is appended to the `web` group, not invoked per response. |
| `Controllers/` → `Requests/` → type catalogue | Capture validation delegated to the form request, whose rules derive from the catalogue. |
| `Controllers/` → Domain Services (outbound) | Preprocessing on *Process*, sanitisation on *Send*, and the type-catalogue projection for Inertia props and `GET /types`. |
| `Controllers/` → Integration Adapters (outbound) | The transcription seam on *Process*, the ticket seam on *Send*. |
| `Controllers/` → Persistence (outbound) | Note CRUD, status, and audio path; the single user record with its credential material; dashboard aggregates. |
| `Controllers/` → type catalogue (outbound) | The GitHub target for the read-only settings view. |

### 5.2.3 Whitebox Domain Services

![Level 2 — Whitebox Domain Services](diagrams-png/a05-l2-domain-services.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | [§ 5.1.3 Blackbox Domain Services](#513-blackbox-domain-services). |
| **Overview diagram** | Figure above; source [`diagrams/a05-l2-domain-services.plantuml`](diagrams/a05-l2-domain-services.plantuml). |
| **Contained building blocks** | Three services — table below. |
| **Local relationships** | Table below — note the absence of relationships *between* the three services. |
| **Design decisions** | Three services, three concerns, no mutual dependencies — each consumes the type catalogue independently, which keeps [N2.2](../spec/N2-querschnittskonzepte.md#n22-type-driven-configuration)'s single resolution point honest. Prompt-stripping lives in the registry, so the leak surface of [NFR-15b-02](../spec/N1-nichtfunktional.md#15b-integrity-requirements) is one method wide. |
| **Rejected alternatives** | One combined `NoteProcessingService` — sanitisation is needed at the dispatch boundary independently of preprocessing. |
| **References** | [§ 8.2](A08-cross-cutting-concepts.md#82-type-driven-configuration), [§ 8.5](A08-cross-cutting-concepts.md#85-content-sanitisation), [chapter 6](A06-runtime-view.md). |
| **Open issues** | Sanitisation is invoked only on `send` — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(b). |

Contained black boxes (all under `app/Services/`):

| Block | Interface | Responsibility |
|-------|-----------|----------------|
| `PreprocessingService` | `process(VoiceNote): void` | Assembles the type-specific prompt from the catalogue (unknown type → exception), calls the generation seam, and persists the generated title, body, and extracted extra fields together with the new status ([§ 8.2](A08-cross-cutting-concepts.md#82-type-driven-configuration), [chapter 6](A06-runtime-view.md)). |
| `IssueContentSanitizer` | `sanitizeAndWrap(VoiceNote): string` | Composes the dispatched issue body from sanitised note content plus a generated metadata section — the neutralisation and visual delimitation demanded by [NFR-15b-04](../spec/N1-nichtfunktional.md#15b-integrity-requirements); rules in [§ 8.5](A08-cross-cutting-concepts.md#85-content-sanitisation). |
| `MessageTypeRegistry` | `all(): array`, `get(string): ?array`, `keys(): array` | The browser-safe projection of the type catalogue: the prompt-bearing entries are stripped before anything reaches the UI Shell — the enforcement point of [NFR-15b-02](../spec/N1-nichtfunktional.md#15b-integrity-requirements). |

Local relationships:

| Relationship | Contract |
|--------------|----------|
| Web Edge → the three services (inbound) | `process(note)` on *Process* ([UC-06](../spec/F2-anwendungsfaelle.md#uc-06--process-voice-note)); `sanitizeAndWrap(note)` on *Send* ([UC-08](../spec/F2-anwendungsfaelle.md#uc-08--dispatch-voice-note)); `all()`/`get()`/`keys()` for Inertia props and `GET /types`. |
| `PreprocessingService` → Integration Adapters (outbound) | `chat()` — the generation seam. |
| `PreprocessingService` → Persistence (outbound) | Generated title and body, merged metadata, new status. |
| all three → type catalogue (outbound) | Prompt and extraction schema (preprocessing); extra-field labels for the metadata section (sanitizer); the stripped catalogue (registry). |

### 5.2.4 Whitebox Integration Adapters

![Level 2 — Whitebox Integration Adapters](diagrams-png/a05-l2-integration-adapters.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | [§ 5.1.4 Blackbox Integration Adapters](#514-blackbox-integration-adapters). |
| **Overview diagram** | Figure above; source [`diagrams/a05-l2-integration-adapters.plantuml`](diagrams/a05-l2-integration-adapters.plantuml). |
| **Contained building blocks** | Two classes carrying three seams — table below. |
| **Local relationships** | Table below; the only HTTPS calls Herold makes to neighbours. There is no relationship between the two classes. |
| **Design decisions** | Three logical seams — one per neighbour NB-02/NB-03/NB-04 ([S1.3](../spec/S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)–[S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api)) — realised in two classes: the two OpenAI seams share credential and base URL and therefore live in one class, `AIService`; the GitHub seam is its own class. |
| **Rejected alternatives** | One class per seam — deferred, not excluded (see [§ 5.3.1](#531-whitebox-aiservice)); vendor SDKs — unnecessary weight for three endpoints. |
| **References** | [S1.3](../spec/S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)–[S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api), [N2.5](../spec/N2-querschnittskonzepte.md#n25-failure-handling), [§ 8.11](A08-cross-cutting-concepts.md#811-development-and-test-concept). |
| **Open issues** | Hard-coded model identifiers — [R-07](A11-risks-and-technical-debts.md#111-risks); the SDK named in [TECH-08](A02-architecture-constraints.md#21-technical-constraints) is not the one used — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(e). |

Contained black boxes (all under `app/Services/`):

| Block | Interface | Responsibility |
|-------|-----------|----------------|
| `AIService` | `transcribe(string): string`, `chat(string, string, float): array` | The AI provider boundary ([QG-04](A01-introduction-and-goals.md#12-quality-goals)); credential from the configuration, construction fails fast if unset. Bundles the transcription seam (NB-02) and the generation seam (NB-03) — opened as a whitebox in [§ 5.3.1](#531-whitebox-aiservice). |
| `GitHubService` | `createIssue(string, string, array): array` | The ticket seam (NB-04): the one-way issue push of [S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api), with token and repository coordinates from the configuration. Returns the issue reference stored on the note; any non-success response throws. |

Local relationships:

| Relationship | Contract |
|--------------|----------|
| Web Edge → `AIService`, `GitHubService` (inbound) | `transcribe()` on *Process*, `createIssue()` on *Send*. |
| Domain Services → `AIService` (inbound) | `chat()` from `PreprocessingService`. |
| both classes → type catalogue (outbound) | The two credentials plus the repository target — both fail fast when unset. |
| `AIService` → OpenAI (outbound, external) | The two HTTPS endpoints of NB-02 and NB-03, with their own timeouts — detailed in [§ 5.3.1](#531-whitebox-aiservice). |
| `GitHubService` → GitHub Issues (outbound, external) | Token-authenticated HTTPS issue creation ([S1.5](../spec/S1-nachbarsysteme.md#s15--nb-04--github-issues-api)). |

---

## 5.3 Level 3

Level 3 opens the one level-2 black box whose inner structure carries an architectural decision: `AIService`, where two *logically independent* seams share one class. `GitHubService` (single operation), the controllers (flat action methods), and the UI components (leaf files) end their branches at level 2 — refining them would only restate code.

### 5.3.1 Whitebox AIService

![Level 3 — Whitebox AIService](diagrams-png/a05-l3-aiservice.png)

| Whitebox | Content |
|----------|---------|
| **Whitebox of** | `AIService`, a black box of [§ 5.2.4](#524-whitebox-integration-adapters). |
| **Overview diagram** | Figure above; source [`diagrams/a05-l3-aiservice.plantuml`](diagrams/a05-l3-aiservice.plantuml). |
| **Contained building blocks** | Two seams plus shared plumbing — table below. |
| **Local relationships** | Both seams use the shared plumbing and neither knows the other; each calls its own OpenAI endpoint (see the contracts below). |
| **Design decisions** | The spec treats transcription (NB-02, [S1.3](../spec/S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api)) and generation (NB-03, [S1.4](../spec/S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api)) as distinct neighbours, and [NFR-14c-01](../spec/N1-nichtfunktional.md#14c-adaptability-requirements) demands that each be replaceable independently. Inside the class the two seams share nothing but the Bearer credential and the base URL — a provider switch cuts along exactly these lines, so the seams are documented as separate building blocks even though both are methods of `app/Services/AIService.php` today. |
| **Rejected alternatives** | One class per seam — it would duplicate credential handling for no present gain, but becomes mandatory the moment the two seams stop sharing a provider (e.g. transcription moves to a local model); until then the shared-class realisation is the simpler, equally seam-faithful choice. |
| **References** | [S1.3](../spec/S1-nachbarsysteme.md#s13--nb-02--openai-whisper-api), [S1.4](../spec/S1-nachbarsysteme.md#s14--nb-03--openai-chat-completion-api), [chapter 6](A06-runtime-view.md). |
| **Open issues** | Both model identifiers are hard-coded in the seams instead of being host-configured — [R-07](A11-risks-and-technical-debts.md#111-risks), [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(f). |

Contained black boxes:

| Block | Realisation | Contract |
|-------|-------------|----------|
| **Transcription seam** | `transcribe(string $audioPath): string` | `POST /v1/audio/transcriptions`, model `gpt-4o-transcribe`, multipart file attach, `response_format: text`, timeout 120 s. Returns the plain-text transcript; any non-success response throws (`Whisper transcription failed: …`). Serves [UC-06](../spec/F2-anwendungsfaelle.md#uc-06--process-voice-note) step 3. |
| **Generation seam** | `chat(string $systemPrompt, string $userMessage, float $temperature = 0.3): array` | `POST /v1/chat/completions`, model `gpt-5.4-mini`, `response_format: json_object`, temperature 0.3. Parses `choices.0.message.content` and requires `title` + `body` keys, else throws. Serves [UC-06](../spec/F2-anwendungsfaelle.md#uc-06--process-voice-note) step 4 (called by `PreprocessingService`). |
| **Shared plumbing** | Constructor + private state | Bearer key from `config('herold.openai.api_key')` (fail-fast `RuntimeException` when unset), base URL `https://api.openai.com/v1`, Laravel `Http` facade. |
