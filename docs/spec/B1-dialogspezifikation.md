# B1 — Dialogue Specification

Dialogue specification in the sense of Siedersleben (chapter 4.5): the operator-facing screens of Herold, the navigation between them, and the dialogue patterns shared across screens. B1 describes **what each screen offers the operator** and **how screens connect** — independently of any visual design language, component library, or front-end technology. Visual identity (colour, typography, glow, spacing) is *not* part of B1; it is fixed in `DESIGN.md` and consumed uniformly by every screen described here.

Each screen realises one or more use cases from F2; conversely, every operator-meaningful step in F2 is rendered by exactly one screen in B1. The mapping is recorded in B1.1 and re-stated per screen in B1.3. Numbering of dialogue identifiers (`DLG-xx`) is stable; once referenced from another block, an ID is not renumbered.

---

## B1.1 Dialogue Index

| ID | Screen | Group | Realises UC | Auth required |
|----|--------|-------|-------------|---------------|
| [DLG-01](#dlg-01--sign-in-first-factor) | Sign-in (first factor) | Access | UC-01 (steps 1–3) | no |
| [DLG-02](#dlg-02--second-factor-verification) | Second-factor verification | Access | UC-01 (step 5) | no (mid-flow) |
| [DLG-03](#dlg-03--second-factor-enrolment) | Second-factor enrolment | Access | UC-02 | no (mid-flow) |
| [DLG-04](#dlg-04--recovery) | Recovery | Access | UC-03 (steps 1–4) | no |
| [DLG-05](#dlg-05--recovery-result) | Recovery result | Access | UC-03 (step 6) | yes (post-recovery) |
| [DLG-06](#dlg-06--dashboard) | Dashboard | Common | — (entry hub) | yes |
| [DLG-07](#dlg-07--notes-list) | Notes list | Management | UC-09 | yes |
| [DLG-08](#dlg-08--note-detail) | Note detail | Management / Note flow | UC-10 (host); UC-06, UC-07, UC-08, UC-11 (entered from here) | yes |
| [DLG-09](#dlg-09--capture-voice-note) | Capture voice note | Note flow | UC-05 | yes |
| [DLG-10](#dlg-10--settings) | Settings | Configuration | UC-12 | yes |

The system-wide chrome (application header, navigation, sign-out control) is described in B1.4 rather than as a standalone dialogue, since it is composed onto every authenticated screen and carries no goal of its own.

![B1 Dialogue Navigation Map — Herold](diagrams-png/b1-navigation.png)

The navigation map shows how the operator moves between dialogues. The two cluster backgrounds separate the unauthenticated *Access* dialogues from those reached through an *Authenticated session*. Per-dialogue fill colour mirrors the encoding used in F2: warm tones for dialogues reachable without an established session, cool tone for dialogues that require one (DLG-05 sits in between — the recovery has succeeded, but no session has yet been established).

Within the authenticated cluster the persistent navigation chrome (side nav on desktop, bottom nav on mobile) is summarised in a side note rather than as N×N edges, since every authenticated screen offers the same four targets — *Dashboard*, *Record*, *Notes*, *Settings* — plus the *Sign out* control that leaves the cluster entirely (see B1.4). DLG-10 *Settings* therefore appears in the map without dedicated workflow edges; it is reached exclusively via this chrome.

---

## B1.2 Per-Screen Specification Template

Each entry in B1.3 carries a summary table followed by two formal subsections (*GUI Statik* and *GUI Dynamik*). The summary captures intent and qualities; the formal subsections enumerate every field and every action.

### B1.2.1 Summary table

| Section | Meaning |
|---------|---------|
| **Identifier** | Stable `DLG-xx` ID. |
| **Name** | Human-readable screen name. |
| **Realises** | UC(s) from F2 and the specific scenario steps rendered here. |
| **Purpose** | One sentence on what the operator accomplishes on this screen. |
| **Entry points** | How the operator reaches this screen. |
| **Layout regions** | Logical regions of the screen (header, primary content, action area, side panels), independent of pixel layout. |
| **Outcomes** | Resulting screen transitions per action (success, failure, cancel). The exhaustive enumeration of fields lives in *GUI Statik* below; the exhaustive enumeration of actions lives in *GUI Dynamik* below. |
| **Validation** | Dialogue-level validation rules. Algorithmic validation lives in [N2](N2-querschnittskonzepte.md) *Validation* (backed by [D2.7](D2-datentypen.md#d27-typespecificdata)); here only what the operator sees and when. |
| **Empty / loading / error states** | What the screen presents when there is no data, while a synchronous operation runs, or when the last action failed. |
| **Qualities** | Cross-references into N1 (NFRs), N2 (cross-cutting concepts) where relevant. |

### B1.2.2 GUI Statik

A table enumerating every field on the dialogue. Field rows in the order they appear on the screen.

| Column | Meaning |
|--------|---------|
| **Field** | The label the operator sees, or — for unlabeled visual elements — a short noun phrase. |
| **Kind** | One of: `display` (read-only output), `input (mandatory)`, `input (optional)`. |
| **Mapping** | Reference into [D1](D1-datenmodell.md) as `Entity.attribute`, or `—` for fields without a persistent counterpart (e.g. transient inputs verified against a hash, or framing-only elements). Multiple mappings are listed comma-separated when one field reflects more than one attribute. |
| **Default** | The pre-filled value, the rule by which it is derived, or `—` when the field is empty on entry. |

### B1.2.3 GUI Dynamik

A list enumerating every action-triggering control of the dialogue. Each action carries:

- **Trigger** — the gesture or control that fires the action (e.g. *Click on the Verify button*).
- **Preconditions** — conditions that must hold for the action to be allowed; `None` if the action is always available.
- **Effect** — what happens, expressed as a target screen transition and/or the F2 use case step the action drives.

### B1.2.4 Multi-state dialogues

Where a dialogue carries genuinely different fields and actions across its lifecycle states (DLG-08 across `recorded` / `processed` / *editing* / `sent`; DLG-09 across *idle* / *recording* / *review*), the *GUI Statik* and *GUI Dynamik* subsections are split per state with a heading naming the state. Cross-cutting loading and error appearances (e.g. DLG-08 *processing* and *error*) are not separate states; they are governed by [§B1.4.3](#b143-synchronous-operation-feedback) and [§B1.4.4](#b144-synchronous-error-handling) and are referenced from the per-state entries rather than re-tabulated.

> [!NOTE]
> **Notation for state names.** State names are typeset in two distinct styles to reflect what kind of state they denote:
>
> - **Backticks** — persisted enumeration values from [D2.5 NoteStatusDT](D2-datentypen.md#d25-notestatusdt) (`recorded`, `processed`, `sent`). These are the actual values stored in `VoiceNote.status` and visible across the system; a state change is a persisted transition in the data model.
> - *Italics* — ephemeral UI states of the dialogue itself, not persisted and not part of [D1](D1-datenmodell.md)/[D2](D2-datentypen.md). They live only within the current rendering of the screen. *editing* in DLG-08 is a UI overlay while the underlying note remains in `processed`; *idle*, *recording*, and *review* in DLG-09 are client-side states before any persistence has occurred.
>
> This distinction matters because per-state subsections describe two different things depending on the style: backtick states partition the dialogue by data status (the same screen presents differently for different stored values), while italic states partition it by interaction phase (the same stored value is presented differently as the operator progresses through the dialogue).

---

## B1.3 Dialogue Specifications

The screenshots embedded in this section are rendered from the static mockups in `poc-ui/`; they document the dialogue-level intent, not a binding visual design (see [§B1.5](#b15-out-of-scope-for-b1)).

### B1.3.1 Access

#### DLG-01 — Sign-in (first factor)

![DLG-01 — Sign-in (first factor)](screenshots/dlg-01-signin-key.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-01 |
| **Name** | Sign-in (first factor) |
| **Realises** | [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) steps 1–3. |
| **Purpose** | Authenticate the operator's first factor (the API key) and route them onward — to inline second-factor enrolment (DLG-03) on the first sign-in, or to second-factor verification (DLG-02) on every subsequent sign-in. |
| **Entry points** | Operator opens Herold without an active session; redirected here from any authenticated screen by the *Unauthenticated redirect* pattern ([§B1.4.2](#b142-unauthenticated-redirect)); returned here after [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out) *Sign out*. |
| **Layout regions** | Brand header; single-column form region with the API-key field; primary action; secondary recovery link. |
| **Outcomes** | *First sign-in (no confirmed second factor bound):* DLG-03.<br>*Subsequent sign-in (confirmed second factor bound):* DLG-02.<br>*Verification fails:* remains on DLG-01 with a generic error; retry within the rate limit.<br>*Recover Access:* DLG-04. |
| **Validation** | Dialogue-level: API key field must not be empty. Algorithmic verification of the key against the stored credential lives in [N2](N2-querschnittskonzepte.md). |
| **Empty / loading / error states** | *Empty:* clean field on first display.<br>*Loading:* *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)) while verification runs.<br>*Error:* *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)) and *Form validation feedback* ([§B1.4.6](#b146-form-validation-feedback)); rate-limit lockout surfaces per [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*. |
| **Qualities** | [NFR-15a-01](N1-nichtfunktional.md) *Two-Factor Browser Authentication*; [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*. |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Brand identity (logo, product name, tagline) | display | — | Static. |
| API Key | input (mandatory) | `Operator.apiKeyHash` (verification target; the entered value is hashed and compared in constant time per [D2.6](D2-datentypen.md#d26-opaquesecret), it is never persisted in clear) | — |

##### GUI Dynamik

**Verify**

- **Trigger:** Click on the *Verify* button (or `Enter` while focus is in the API-key field).
- **Preconditions:** The *API Key* field is non-empty. Subject to [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout* — the action is rejected when the operator is currently locked out.
- **Effect:** Submits the entered value for first-factor verification ([UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) step 3). On a *first* sign-in (no confirmed second factor bound) routes to DLG-03; on a *subsequent* sign-in (confirmed second factor bound) routes to DLG-02. On verification failure the dialogue stays on DLG-01 and surfaces a generic error per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)).

**Recover Access**

- **Trigger:** Click on the *Recover Access* link.
- **Preconditions:** None.
- **Effect:** Routes to DLG-04 to start [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access).

#### DLG-02 — Second-factor verification

![DLG-02 — Second-factor verification](screenshots/dlg-02-totp-verify.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-02 |
| **Name** | Second-factor verification |
| **Realises** | [UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) step 5 (subsequent sign-in branch). |
| **Purpose** | Verify the time-based one-time password as the second factor on a subsequent sign-in, and complete the establishment of the authenticated session. |
| **Entry points** | Reached only from DLG-01 after a successful first-factor verification, when a confirmed second-factor secret is bound to the account. |
| **Layout regions** | Brand header; instruction text; single-column form region with the one-time-code field; primary action; secondary recovery and back links. |
| **Outcomes** | *Verification succeeds:* authenticated session established; route to DLG-06.<br>*Verification fails:* remains on DLG-02 with a generic error; retry within the rate limit.<br>*Recover Access:* DLG-04. |
| **Validation** | Dialogue-level: code field is required and accepts exactly six numeric digits. |
| **Empty / loading / error states** | *Loading* and *error* per *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)) and *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)); rate-limit lockout per [NFR-15a-02](N1-nichtfunktional.md). |
| **Qualities** | [NFR-15a-01](N1-nichtfunktional.md) *Two-Factor Browser Authentication*; [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*. |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Brand identity (logo, product name, tagline) | display | — | Static. |
| Step indicator (two dots, second active) | display | — | Reflects mid-flow position; not operator-editable. |
| Instruction text | display | — | "Enter the 6-digit code from your authenticator app." |
| TOTP Code | input (mandatory) | `Operator.totpSecret` (verification target; the entered code is computed against the stored shared secret in constant time per [D2.6](D2-datentypen.md#d26-opaquesecret)) | — |

##### GUI Dynamik

**Login**

- **Trigger:** Click on the *Login* button (or `Enter` while focus is in the TOTP-code field).
- **Preconditions:** The *TOTP Code* field contains exactly six numeric digits. Subject to [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout*.
- **Effect:** Submits the code for second-factor verification ([UC-01](F2-anwendungsfaelle.md#uc-01--sign-in) step 5). On success establishes the authenticated session and routes to DLG-06. On verification failure the dialogue stays on DLG-02 and surfaces a generic error per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)).

**Recover Access**

- **Trigger:** Click on the *Recover Access* link.
- **Preconditions:** None.
- **Effect:** Routes to DLG-04 to start [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access). The in-flight first-factor verification is abandoned; the recovery flow does not depend on it.

**Back**

- **Trigger:** Click on the *Back* link.
- **Preconditions:** None.
- **Effect:** Routes to DLG-01. The in-flight sign-in is abandoned; on the next attempt the API key has to be re-entered.

#### DLG-03 — Second-factor enrolment

![DLG-03 — Second-factor enrolment](screenshots/dlg-03-totp-setup.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-03 |
| **Name** | Second-factor enrolment |
| **Realises** | [UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor). |
| **Purpose** | Bind a fresh second-factor secret to the account and have the operator confirm it from their authenticator, so that subsequent sign-ins (DLG-02) can verify it. |
| **Entry points** | Inline from DLG-01 when no confirmed second-factor secret is bound (first sign-in); reached again after [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) recovery has unbound the prior secret and the operator returns to sign in. |
| **Layout regions** | Brand header; instruction text; provisioning region rendering the new secret in a form an authenticator app can capture (and as raw fallback); single-column form region with the confirmation-code field; primary action; back link. |
| **Outcomes** | *Confirmation succeeds:* authenticated session established; route to DLG-06.<br>*Confirmation fails:* remains on DLG-03; the secret stays provisional; retry. |
| **Validation** | Dialogue-level: code field is required and accepts exactly six numeric digits. |
| **Empty / loading / error states** | *One-time secret display* ([§B1.4.8](#b148-one-time-secret-display)) — the raw secret is rendered on this screen only and is not retrievable later; if the operator abandons setup before confirming, [UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor) is repeated and a new secret is generated. *Loading* and *error* per [§B1.4.3](#b143-synchronous-operation-feedback) and [§B1.4.4](#b144-synchronous-error-handling). |
| **Qualities** | [NFR-15a-01](N1-nichtfunktional.md) *Two-Factor Browser Authentication*. No backup-code list is offered; the recovery path for a lost authenticator is DLG-04 (UC-03). |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Brand identity (logo, product name, tagline) | display | — | Static. |
| Step indicator (two dots, second active) | display | — | Reflects mid-flow position; not operator-editable. |
| Instruction text | display | — | "Scan this QR code with your authenticator app, then enter the code below." |
| Provisioning material (QR code rendering of the freshly bound TOTP secret) | display | `Operator.totpSecret` (the unconfirmed value is rendered here exactly once; treated per *One-time secret display* [§B1.4.8](#b148-one-time-secret-display) — the underlying value is an [`OpaqueSecret`](D2-datentypen.md#d26-opaquesecret) and is never re-shown by the system) | Generated when the dialogue opens. |
| Verification Code | input (mandatory) | `Operator.totpSecret` (verification target; entering a valid code computed from the displayed secret marks the secret as confirmed and sets `Operator.totpConfirmedAt`) | — |

##### GUI Dynamik

**Verify & Login**

- **Trigger:** Click on the *Verify & Login* button (or `Enter` while focus is in the verification-code field).
- **Preconditions:** The *Verification Code* field contains exactly six numeric digits.
- **Effect:** Submits the code for confirmation ([UC-02](F2-anwendungsfaelle.md#uc-02--enrol-second-factor) step 5). On success persists the secret as confirmed (`Operator.totpConfirmedAt` set), establishes the authenticated session, and routes to DLG-06. On confirmation failure the dialogue stays on DLG-03 — the secret remains provisional and the operator can retry; an explicit re-issue happens by leaving and returning to enrolment.

**Back**

- **Trigger:** Click on the *Back* link.
- **Preconditions:** None.
- **Effect:** Routes to DLG-01 without confirming the provisional secret. The unconfirmed value is replaced on the next enrolment attempt; no second factor is bound until a confirmation succeeds.

#### DLG-04 — Recovery

![DLG-04 — Recovery](screenshots/dlg-04-recovery-form.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-04 |
| **Name** | Recovery |
| **Realises** | [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) steps 1–4. |
| **Purpose** | Let the operator regain access to a locked-out account by redeeming a one-time recovery token they placed on the host out-of-band. |
| **Entry points** | Reached from DLG-01 or DLG-02 via the *Recover Access* link. |
| **Layout regions** | Brand header; instruction text describing the out-of-band precondition (operator must have placed a recovery token on the host); single-column form region with the recovery-secret field; primary action; back link to DLG-01. |
| **Outcomes** | *Redemption succeeds:* authenticated session established by the system; the bound second factor is unbound, a fresh API key is generated, and the screen transitions to DLG-05 to display it.<br>*Redemption fails (no token present, token expired, or entered string does not match):* remains on DLG-04 with a single generic rejection that does not disclose which condition was hit; rate-limited and logged. |
| **Validation** | Dialogue-level: secret field must not be empty. The token's existence, age, and content are checked server-side and surface here as one undifferentiated rejection per [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) exception scenarios. |
| **Empty / loading / error states** | *Loading* per *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)); *error* per *Synchronous error handling*, but with the generic-rejection wording stipulated by UC-03. |
| **Qualities** | [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout* (recovery branch); [NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry*. |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Brand identity (logo, product name, *Account Recovery* tagline) | display | — | Static. |
| Instruction text | display | — | "Place your recovery token file on the host, then enter the secret string you wrote into it." |
| Recovery Token | input (mandatory) | `RecoveryToken.token` (verification target; entered string is compared in constant time per [D2.6](D2-datentypen.md#d26-opaquesecret) against the operator-placed artefact, whose age is bounded by [NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry*) | — |

##### GUI Dynamik

**Recover Account**

- **Trigger:** Click on the *Recover Account* button (or `Enter` while focus is in the recovery-token field).
- **Preconditions:** The *Recovery Token* field is non-empty. Subject to [NFR-15a-02](N1-nichtfunktional.md) *Login Rate Limiting and Lockout* (recovery branch).
- **Effect:** Submits the entered string for redemption ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) step 4). On success the system deletes the on-host artefact, clears `Operator.totpSecret` and `Operator.totpConfirmedAt`, generates a fresh API key (replacing `Operator.apiKeyHash`), establishes a session, and routes to DLG-05 to display the new key. On failure (no token present, token expired, or string does not match) the dialogue stays on DLG-04 with a single generic rejection per [NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry* — the three failure modes are deliberately conflated.

**Back to Login**

- **Trigger:** Click on the *Back to Login* link.
- **Preconditions:** None.
- **Effect:** Routes to DLG-01 without redeeming. Any token placed on the host remains in place until it expires per [NFR-15a-04](N1-nichtfunktional.md).

#### DLG-05 — Recovery result

![DLG-05 — Recovery result](screenshots/dlg-05-recovery-result.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-05 |
| **Name** | Recovery result |
| **Realises** | [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) step 6 (one-time display of the new API key). |
| **Purpose** | Display the freshly generated API key to the operator exactly once so they can record it before leaving the screen. |
| **Entry points** | Reached automatically after a successful redemption on DLG-04. The screen is unreachable by direct navigation. |
| **Layout regions** | Brand header (visually distinct from the primary recovery cluster — the *One-time secret display* pattern in [§B1.4.8](#b148-one-time-secret-display) marks the screen as a one-shot); success indicator; new-API-key region with copy affordance; warning region restating the irretrievability of the key and the requirement to re-enrol the second factor on the next sign-in; primary action to leave the cluster. |
| **Outcomes** | *Continue:* DLG-01.<br>*Operator closes the screen before recording the key:* the key cannot be retrieved; the operator must restart [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) with a freshly placed recovery token. |
| **Validation** | Not applicable (no operator input). |
| **Empty / loading / error states** | Not applicable; this screen has only a success representation. |
| **Qualities** | *One-time secret display* ([§B1.4.8](#b148-one-time-secret-display)). The screen is reachable only with a session already established; it sits in the unauthenticated cluster of B1.1's navigation map only because no second factor is yet bound. |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Brand identity (logo, product name, *Account Recovered* tagline) | display | — | Static. |
| Success indicator | display | — | "Your account has been successfully recovered." |
| New API Key | display | `Operator.apiKeyHash` (the *raw* freshly generated key is rendered here exactly once; only its hash is persisted, the clear value is treated per *One-time secret display* [§B1.4.8](#b148-one-time-secret-display) and not retrievable later) | Generated by the redemption in DLG-04. |
| Warning text | display | — | "Save this API key now. It will not be shown again. You will also need to set up TOTP again on your next login." |

##### GUI Dynamik

**Copy**

- **Trigger:** Click on the *Copy* button.
- **Preconditions:** None.
- **Effect:** Copies the displayed new API key to the operator's clipboard. The dialogue does not transition.

**Go to Login**

- **Trigger:** Click on the *Go to Login* button.
- **Preconditions:** None.
- **Effect:** Leaves the recovery cluster and routes to DLG-01. The next sign-in will route through DLG-03 to enrol a new second factor, since `Operator.totpConfirmedAt` was cleared by the redemption.

### B1.3.2 Common

#### DLG-06 — Dashboard

![DLG-06 — Dashboard](screenshots/dlg-06-dashboard.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-06 |
| **Name** | Dashboard |
| **Realises** | — (entry hub after sign-in; no F2 use case of its own). |
| **Purpose** | Give the operator an at-a-glance view of their voice-note activity by status, surface the most recent notes, and offer the most common forward action — capturing a new note. |
| **Entry points** | Default destination after a successful sign-in (UC-01); *Dashboard* item in the persistent navigation chrome ([§B1.4.1](#b141-application-chrome)). |
| **Layout regions** | Persistent navigation chrome; page header; status-overview tiles (one per processing state, with counts); primary capture entry point; recent-notes region listing the last few notes with title, summary, status, message-type indicator, issue reference (if dispatched), and timestamp. |
| **Outcomes** | Navigational only; the dashboard itself does not change state. |
| **Validation** | Not applicable. |
| **Empty / loading / error states** | *Empty:* when no notes exist, the recent-notes region uses the *Empty states* pattern ([§B1.4.7](#b147-empty-states)); the *New Recording* action remains visible.<br>*Loading/error* of the underlying data follows the cross-cutting patterns in [§B1.4](#b14-cross-cutting-dialogue-patterns). |
| **Qualities** | *Application chrome* ([§B1.4.1](#b141-application-chrome)). |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Page title | display | — | "Dashboard". |
| Page subtitle | display | — | "Overview of your voice notes and processing status." |
| *Total Notes* count | display | Aggregation: count of `VoiceNote` rows | Live count. |
| *Recorded* count | display | Aggregation: count of `VoiceNote` rows where `VoiceNote.status` = `recorded` (per [D2.5](D2-datentypen.md#d25-notestatusdt)) | Live count. |
| *Processed* count | display | Aggregation: count of `VoiceNote` rows where `VoiceNote.status` = `processed` | Live count. |
| *Sent* count | display | Aggregation: count of `VoiceNote` rows where `VoiceNote.status` = `sent` | Live count. |
| *Errors* count | display | Aggregation: count of `VoiceNote` rows where `VoiceNote.errorMessage` is non-empty (orthogonal to status — see [D2.5](D2-datentypen.md#d25-notestatusdt) *Failure handling*) | Live count. |
| Recent-notes section heading | display | — | "Recent Notes". |
| Recent-note row — type indicator | display | `VoiceNote.type` (per [D2.4](D2-datentypen.md#d24-messagetypedt)) | — |
| Recent-note row — title | display | `VoiceNote.processedTitle` if set, else a placeholder for the unprocessed note | — |
| Recent-note row — snippet | display | Excerpt derived from `VoiceNote.processedBody` (or transcript when not yet processed) | — |
| Recent-note row — status badge | display | `VoiceNote.status`, with the *error* visual variant driven by `VoiceNote.errorMessage` being non-empty | — |
| Recent-note row — issue reference | display | `VoiceNote.githubIssueNumber` (rendered as `#<n>`); shown only when present | — |
| Recent-note row — timestamp | display | `VoiceNote.createdAt` | — |

##### GUI Dynamik

**New Recording**

- **Trigger:** Click on the *New Recording* button.
- **Preconditions:** None.
- **Effect:** Routes to DLG-09 to start [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note).

**Open recent note**

- **Trigger:** Click on a row in the *Recent Notes* list.
- **Preconditions:** None.
- **Effect:** Routes to DLG-08 for the selected `VoiceNote` ([UC-10](F2-anwendungsfaelle.md#uc-10--view-a-voice-note)).

**Navigate via chrome**

- **Trigger:** Click on a destination in the persistent navigation chrome ([§B1.4.1](#b141-application-chrome)).
- **Preconditions:** None.
- **Effect:** Routes to DLG-06 / DLG-09 / DLG-07 / DLG-10 according to the chosen destination, or to [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out) on *Sign out*. Documented once in [§B1.4.1](#b141-application-chrome) and not repeated on every authenticated dialogue's *GUI Dynamik*.

### B1.3.3 Note Flow

#### DLG-09 — Capture voice note

The screen has three states reflecting the capture lifecycle. The operator moves through them in order, but may discard at any point and return to *idle*.

*Idle — ready to record:*

![DLG-09 (idle)](screenshots/dlg-09-capture-idle.png)

*Recording — active capture:*

![DLG-09 (recording)](screenshots/dlg-09-capture-recording.png)

*Review — playback after recording:*

![DLG-09 (review)](screenshots/dlg-09-capture-review.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-09 |
| **Name** | Capture voice note |
| **Realises** | [UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note). |
| **Purpose** | Let the operator pick a message type, record an audio note in the browser, supply any extra fields the type declares, and submit the result for later processing. |
| **Entry points** | *Record* item in the navigation chrome; *New Recording* action on DLG-06. |
| **Layout regions** | Navigation chrome; page header; message-type selector listing the configured message types ([E2](E2-glossar.md) *message type*); type-driven extra-fields region (revealed only when the active type declares extra fields per [D2.4](D2-datentypen.md#d24-messagetypedt)/[D2.7](D2-datentypen.md#d27-typespecificdata)); audio capture region with a level indicator, an elapsed-time indicator, a primary record-or-stop control, and a secondary pause control; review region (visible only after capture) with playback, save, and discard. |
| **Outcomes** | *Save succeeds:* DLG-08, with the note in `recorded` state.<br>*Re-record:* discards the in-browser capture, returns to *idle*; nothing is persisted ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) cancellation alternative). |
| **Validation** | Dialogue-level: a message type must be selected; type-declared extra fields are validated per their schema; the captured audio must satisfy [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*. Algorithmic validation lives in [N2](N2-querschnittskonzepte.md) *Validation*. |
| **Empty / loading / error states** | *Idle* (initial): no capture yet; record control prominent.<br>*Recording:* live indicators; record control replaced by stop+pause.<br>*Review:* playback and save/discard.<br>*Error:* per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)) — covers microphone-permission denial, audio that violates the upload constraints, and submission failures. |
| **Qualities** | [NFR-13a-01](N1-nichtfunktional.md) *Mobile Usage on the Go* (the recording region is the primary target on small screens — see *Mobile usage* in [§B1.4.9](#b149-mobile-usage)); [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*. |

The dialogue is multi-state: *idle*, *recording*, *review*. The message-type selector and the type-driven extra-fields region exist across all three states; the recorder region's contents differ. *GUI Statik* and *GUI Dynamik* are split per state below; fields and actions that are identical across all three states are listed once in the *idle* block and referenced from the others.

##### GUI Statik — state *idle*

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Page title | display | — | "New Recording". |
| Page subtitle | display | — | "Select a type, record your voice note, then save." |
| Message Type | input (mandatory) | `VoiceNote.type` (per [D2.4](D2-datentypen.md#d24-messagetypedt)) | The first configured type (currently `general`). |
| Type-specific extra field — *YouTube URL* (visible only when *Message Type* is `youtube`) | input (mandatory) | `VoiceNote.metadata.youtubeUrl` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | — |
| Type-specific extra field — *Entry Date* (visible only when *Message Type* is `diary`) | input (optional) | `VoiceNote.metadata.entryDate` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | — |
| Type-specific extra field — *Vault* (visible only when *Message Type* is `obsidian`) | input (optional) | `VoiceNote.metadata.vault` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | — |
| Type-specific extra field — *Deadline* (visible only when *Message Type* is `todo`) | input (optional) | `VoiceNote.metadata.deadline` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | — |
| Waveform indicator | display | — | Static (idle bars). |
| Elapsed-time indicator | display | — | "00:00". |

##### GUI Dynamik — state *idle*

**Select message type**

- **Trigger:** Click on a chip in the *Message Type* group.
- **Preconditions:** None.
- **Effect:** Sets the bound `VoiceNote.type` for the in-progress capture and reveals or hides the type-driven extra-fields region per [D2.7](D2-datentypen.md#d27-typespecificdata). The recorder region is unaffected.

**Edit a type-specific extra field**

- **Trigger:** Type into the visible extra-field input.
- **Preconditions:** The corresponding *Message Type* is selected.
- **Effect:** Sets the slot value on the in-progress capture; validation per [§B1.4.6](#b146-form-validation-feedback) runs at submission time.

**Start recording**

- **Trigger:** Click on the record control.
- **Preconditions:** A *Message Type* is selected (the dialogue enforces a default selection on entry, so this is implicitly satisfied). The browser has been granted microphone permission; if denied, *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)) surfaces the permission failure and the dialogue stays *idle*.
- **Effect:** Transitions the dialogue to state *recording*; capture begins ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) step 4).

##### GUI Statik — state *recording*

The fields above (page title, page subtitle, *Message Type*, type-specific extras) remain present and unchanged. Additionally:

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Live waveform / level indicator | display | — | Live audio level. |
| Elapsed-time indicator | display | — | Live MM:SS timer since *Start recording*. |

##### GUI Dynamik — state *recording*

**Pause / Resume**

- **Trigger:** Click on the pause control (toggles between *Pause* and *Resume*).
- **Preconditions:** The dialogue is in state *recording*.
- **Effect:** Suspends or resumes capture without committing. The elapsed-time indicator continues from where it was.

**Stop**

- **Trigger:** Click on the stop control.
- **Preconditions:** The dialogue is in state *recording*.
- **Effect:** Ends capture and transitions the dialogue to state *review*. Nothing has been persisted yet — the captured audio lives only in the browser.

The actions on the *Message Type* selector and on the type-specific extra fields remain available unchanged and are not re-listed here.

##### GUI Statik — state *review*

The fields above (page title, page subtitle, *Message Type*, type-specific extras) remain present and unchanged. The recorder region now contains:

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Playback progress bar | display | — | Reflects the playhead within the captured audio. |
| Elapsed / total time indicator | display | — | Live MM:SS / MM:SS. |

##### GUI Dynamik — state *review*

**Play / Pause playback**

- **Trigger:** Click on the play/pause control.
- **Preconditions:** A capture exists (state *review*).
- **Effect:** Starts or pauses local playback of the captured audio; the dialogue stays in *review*. Nothing is persisted.

**Re-record (discard local capture)**

- **Trigger:** Click on the discard control.
- **Preconditions:** A capture exists (state *review*).
- **Effect:** Discards the in-browser capture and transitions back to state *idle* ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) cancellation alternative). The *Message Type* selection and any extra-field values are preserved.

**Save Voice Note**

- **Trigger:** Click on the *Save Voice Note* button.
- **Preconditions:** A capture exists (state *review*); the captured audio satisfies [NFR-15a-03](N1-nichtfunktional.md) *Audio Upload Validation*; type-specific extra fields satisfy their declared schema per [D2.7](D2-datentypen.md#d27-typespecificdata).
- **Effect:** Submits the recording and metadata ([UC-05](F2-anwendungsfaelle.md#uc-05--capture-voice-note) step 6). On success creates a new `VoiceNote` row with `VoiceNote.status` = `recorded`, `VoiceNote.type` set, `VoiceNote.audioPath` populated, and `VoiceNote.metadata` populated per the bound type's slot inventory; routes to DLG-08 for the new note. On failure stays in *review* and surfaces the failure inline per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)); validation violations surface inline per *Form validation feedback* ([§B1.4.6](#b146-form-validation-feedback)).

### B1.3.4 Management

#### DLG-07 — Notes list

![DLG-07 — Notes list](screenshots/dlg-07-notes-list.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-07 |
| **Name** | Notes list |
| **Realises** | [UC-09](F2-anwendungsfaelle.md#uc-09--browse-voice-notes). |
| **Purpose** | Let the operator browse the full collection of voice notes ordered by recency, narrow the view by message type or status, and select one for inspection. |
| **Entry points** | *Notes* item in the navigation chrome; *Back to Notes* affordance from DLG-08. |
| **Layout regions** | Navigation chrome; page header; filter region with two narrowing controls (message type and processing status); list region with one row per note, showing message-type indicator, title, summary, status badge, issue reference (if dispatched), timestamp, and an open-detail affordance. |
| **Outcomes** | Selecting a note transitions to DLG-08; changing a filter re-renders the list without leaving DLG-07. |
| **Validation** | Not applicable (filters are pre-constrained selections). |
| **Empty / loading / error states** | *Empty (no notes yet):* *Empty states* pattern ([§B1.4.7](#b147-empty-states)); the operator is invited to capture a note.<br>*Empty after filtering:* explanation that the current filter combination has no matches.<br>*Loading/error* per [§B1.4](#b14-cross-cutting-dialogue-patterns). |
| **Qualities** | *Application chrome* ([§B1.4.1](#b141-application-chrome)) and *Empty states* ([§B1.4.7](#b147-empty-states)). |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Page title | display | — | "Notes". |
| Page subtitle | display | — | "All your voice notes in one place." |
| Type filter | input (optional) | Selection narrows the list by `VoiceNote.type` (per [D2.4](D2-datentypen.md#d24-messagetypedt)) | *All* — no type narrowing. |
| Status filter | input (optional) | Selection narrows the list by `VoiceNote.status` (per [D2.5](D2-datentypen.md#d25-notestatusdt)); the *Error* filter value selects rows where `VoiceNote.errorMessage` is non-empty (orthogonal flag, see [D2.5](D2-datentypen.md#d25-notestatusdt) *Failure handling*) | *All* — no status narrowing. |
| Note row — type indicator | display | `VoiceNote.type` | — |
| Note row — title | display | `VoiceNote.processedTitle` if set, else a placeholder for the unprocessed note | — |
| Note row — snippet | display | Excerpt derived from `VoiceNote.processedBody` (or transcript when not yet processed) | — |
| Note row — status badge | display | `VoiceNote.status`, with the *error* visual variant driven by `VoiceNote.errorMessage` being non-empty | — |
| Note row — issue reference | display | `VoiceNote.githubIssueNumber` (rendered as `#<n>`); shown only when present | — |
| Note row — timestamp | display | `VoiceNote.createdAt` | — |
| Pagination indicator | display | — | Page 1; total pages derived from list size. |

##### GUI Dynamik

**Change type filter**

- **Trigger:** Pick a value in the *Type* selector.
- **Preconditions:** None.
- **Effect:** Re-renders the list region in place per the new narrowing; if the resulting list is empty, the *Empty states* pattern ([§B1.4.7](#b147-empty-states)) applies in its filter-induced variant.

**Change status filter**

- **Trigger:** Pick a value in the *Status* selector.
- **Preconditions:** None.
- **Effect:** Same as *Change type filter*, but narrows by `VoiceNote.status`.

**Open a note row**

- **Trigger:** Click on a note row.
- **Preconditions:** None.
- **Effect:** Routes to DLG-08 for the selected `VoiceNote` ([UC-10](F2-anwendungsfaelle.md#uc-10--view-a-voice-note)).

**Navigate pages**

- **Trigger:** Click on a page number or the next/previous chevron in the pagination control.
- **Preconditions:** The target page exists.
- **Effect:** Re-renders the list region in place with the matching page of `VoiceNote` rows ordered by `VoiceNote.createdAt` descending. The dialogue stays on DLG-07.

**Clear filter** (filter-induced empty state only)

- **Trigger:** Click on the *Clear filter* control rendered by the *Empty states* pattern when the current filter combination has no matches.
- **Preconditions:** The list is currently filter-empty (see [§B1.4.7](#b147-empty-states)).
- **Effect:** Resets *Type* and *Status* to *All* and re-renders the list region.

#### DLG-08 — Note detail

DLG-08 hosts [UC-10](F2-anwendungsfaelle.md#uc-10--view-a-voice-note) and is the entry point for [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content), [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note), and [UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note). The screen renders the note's three lifecycle stages (`recorded`, `processed`, `sent` per [D2.5](D2-datentypen.md#d25-notestatusdt)) as a vertical timeline; the affordances offered in each stage depend on the note's current status.

*Status `recorded` — only audio playback is available; the next forward action is *Process Note* (UC-06):*

![DLG-08 (recorded)](screenshots/dlg-08-note-detail-recorded.png)

*Processing — synchronous loading state during UC-06 (per [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*):*

![DLG-08 (processing)](screenshots/dlg-08-note-detail-processing.png)

*Status `processed` — structured content is visible read-only; *Edit* (UC-07) and *Create Ticket* (UC-08) are offered:*

![DLG-08 (processed)](screenshots/dlg-08-note-detail-processed.png)

*Editing variant of the `processed` stage (UC-07) — title, body, and type-specific extra fields are editable:*

![DLG-08 (editing)](screenshots/dlg-08-note-detail-editing.png)

*Status `sent` — the issue reference produced by UC-08 is displayed; no further forward actions:*

![DLG-08 (sent)](screenshots/dlg-08-note-detail-sent.png)

*Error — surfaces a failed synchronous operation per [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*:*

![DLG-08 (error)](screenshots/dlg-08-note-detail-error.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-08 |
| **Name** | Note detail |
| **Realises** | [UC-10](F2-anwendungsfaelle.md#uc-10--view-a-voice-note) (host); [UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note), [UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content), [UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note), [UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note) — all entered from this screen. |
| **Purpose** | Inspect a single voice note and run any further use case applicable to its current status — process it, edit the generated content, dispatch it as a GitHub issue, or delete it. |
| **Entry points** | Recent-note row on DLG-06; row click on DLG-07. |
| **Layout regions** | Navigation chrome; back-affordance to DLG-07; header with title, status badge, message-type indicator, and timestamp; lifecycle-stage region rendering the three stages (`recorded`, `processed`, `sent`) with the audio playback in the *recorded* stage, the structured content (transcript, generated title, generated body, type-specific extra fields per [D2.7](D2-datentypen.md#d27-typespecificdata)) in the *processed* stage — switching to an editing variant on demand — and the issue reference in the *sent* stage; danger region with the *Delete Note* action. |
| **Outcomes** | *Process / Save / Create Ticket succeed:* the corresponding stage is updated in place; the operator stays on DLG-08 and the next stage's affordances become available.<br>*Delete confirmed:* DLG-07.<br>*Back to Notes:* DLG-07. |
| **Validation** | Dialogue-level: in editing mode, mandatory fields must be non-empty and type-specific extra fields must satisfy their declared schema. Algorithmic validation lives in [N2](N2-querschnittskonzepte.md) *Validation* (backed by [D2.7](D2-datentypen.md#d27-typespecificdata)); markdown sanitisation lives in [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation). |
| **Empty / loading / error states** | *Loading:* *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)) replaces the affected stage's actions with a progress indicator while *Process Note* or *Create Ticket* is in flight ([NFR-12a-01](N1-nichtfunktional.md)).<br>*Error:* *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)) surfaces failures of transcription, content generation, or GitHub dispatch with retry where applicable ([NFR-12d-01](N1-nichtfunktional.md)).<br>*Form validation:* dialogue-level errors render inline in the editing variant. |
| **Qualities** | [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*; [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling*; [NFR-15b-04](N1-nichtfunktional.md) *Issue Content Sanitization* via [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation). |

The dialogue is multi-state: `recorded`, `processed`, *editing* (a sub-state of `processed`), `sent`. The *header* and the *back-affordance* are present and identical across all four states; per state the lifecycle-stage region differs in which stages are populated and which actions are offered. The *processing* and *error* appearances are governed by the cross-cutting patterns in [§B1.4.3](#b143-synchronous-operation-feedback) and [§B1.4.4](#b144-synchronous-error-handling) respectively and are not re-tabulated as separate states.

The header fields below are present in every state; per-state *GUI Statik* tables only list the additional fields that the lifecycle-stage region introduces.

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Header — title | display | `VoiceNote.processedTitle` if set, else a placeholder ("Voice Note") for the unprocessed note | — |
| Header — status badge | display | `VoiceNote.status`, with the *error* visual variant driven by `VoiceNote.errorMessage` being non-empty | — |
| Header — type indicator | display | `VoiceNote.type` (per [D2.4](D2-datentypen.md#d24-messagetypedt)) | — |
| Header — timestamp | display | `VoiceNote.createdAt` | — |

The actions below are present in every state and not re-listed per state:

**Back to Notes**

- **Trigger:** Click on the *Back to Notes* affordance.
- **Preconditions:** None.
- **Effect:** Routes to DLG-07.

**Stream the audio recording**

- **Trigger:** Click on the play control inside the *Recorded* stage card.
- **Preconditions:** None (the audio is bound to the `VoiceNote` for its entire lifetime per [D1](D1-datenmodell.md#voicenote) *Audio document invariants*).
- **Effect:** Streams `VoiceNote.audioPath` from the local audio store. The dialogue does not transition.

**Delete Note**

- **Trigger:** Click on the *Delete Note* button in the danger region.
- **Preconditions:** None — the action is offered in every state.
- **Effect:** Opens the *Confirmation modals* pattern ([§B1.4.5](#b145-confirmation-modals)). On *Cancel* the dialogue is unchanged; on *Confirm* the system runs the synchronous deletion ([UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note)) — the `VoiceNote` row and its bound audio document are removed atomically per [D1](D1-datenmodell.md#voicenote) *Audio document invariants* — and routes to DLG-07.

##### GUI Statik — state `recorded`

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Stage 1 — *Recorded* card heading | display | — | "Recorded". |
| Stage 1 — audio playback control | display | `VoiceNote.audioPath` | — |
| Stage 2 — *Processed* card heading | display | — | "Processed" (greyed out — not yet reached). |
| Stage 3 — *Sent to GitHub* card heading | display | — | "Sent to GitHub" (greyed out — not yet reached). |

##### GUI Dynamik — state `recorded`

**Process Note**

- **Trigger:** Click on the *Process* button in the *Processed* stage card.
- **Preconditions:** The note is in `VoiceNote.status` = `recorded`.
- **Effect:** Triggers the synchronous transcription and content-generation pipeline ([UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note)). While the request is in flight the *Process* control is replaced by a progress indicator per *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)) per [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing*. On success the system populates `VoiceNote.transcript`, `VoiceNote.processedTitle`, `VoiceNote.processedBody`, and any `VoiceNote.metadata` slots resolvable from speech, advances `VoiceNote.status` to `processed`, and the dialogue re-renders in state `processed`. On failure the dialogue stays in state `recorded` and surfaces the failure inline per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)) per [NFR-12d-01](N1-nichtfunktional.md); `VoiceNote.errorMessage` is populated and `VoiceNote.status` does not advance.

##### GUI Statik — state `processed`

The *Recorded* stage card from state `recorded` remains present and unchanged. Additionally:

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Stage 2 — *Transcript* (read-only) | display | `VoiceNote.transcript` | — |
| Stage 2 — *Title* (read-only) | display | `VoiceNote.processedTitle` | — |
| Stage 2 — *Body* (read-only) | display | `VoiceNote.processedBody` | — |
| Stage 3 — *Sent to GitHub* card heading | display | — | "Sent to GitHub" (greyed out — not yet reached). |

##### GUI Dynamik — state `processed`

**Edit**

- **Trigger:** Click on the *Edit* button in the *Processed* stage card.
- **Preconditions:** The note is in `VoiceNote.status` = `processed`.
- **Effect:** Switches the dialogue to state *editing*. The `VoiceNote.status` value does not change.

**Create Ticket**

- **Trigger:** Click on the *Create Ticket* button in the *Sent to GitHub* stage card.
- **Preconditions:** The note is in `VoiceNote.status` = `processed`.
- **Effect:** Triggers the synchronous dispatch to GitHub ([UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note)). Markdown is sanitised before dispatch per [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation). While the request is in flight the *Create Ticket* control is replaced by a progress indicator per *Synchronous operation feedback* ([§B1.4.3](#b143-synchronous-operation-feedback)). On success the system creates a `GitHubIssue` from `VoiceNote.processedTitle` / `VoiceNote.processedBody` (sanitised) / the configured per-type label (per [D2.4](D2-datentypen.md#d24-messagetypedt)), populates `VoiceNote.githubIssueNumber` and `VoiceNote.githubIssueUrl`, advances `VoiceNote.status` to `sent`, and re-renders the dialogue in state `sent`. On failure the dialogue stays in state `processed`, surfaces the failure inline per *Synchronous error handling* ([§B1.4.4](#b144-synchronous-error-handling)), populates `VoiceNote.errorMessage`, and offers the action again.

##### GUI Statik — state *editing*

The *Recorded* stage card and the *Sent to GitHub* stage card remain present and unchanged. Additionally:

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Stage 2 — *Transcript* (read-only reference) | display | `VoiceNote.transcript` | — |
| Stage 2 — *Title* (editable) | input (mandatory) | `VoiceNote.processedTitle` | Pre-filled from current persisted value. |
| Stage 2 — *Body* (editable) | input (mandatory) | `VoiceNote.processedBody` | Pre-filled from current persisted value. |
| Stage 2 — *Extra fields* — *YouTube URL* (visible only when `VoiceNote.type` = `youtube`) | input (mandatory) | `VoiceNote.metadata.youtubeUrl` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | Pre-filled from current persisted value. |
| Stage 2 — *Extra fields* — *Entry Date* (visible only when `VoiceNote.type` = `diary`) | input (optional) | `VoiceNote.metadata.entryDate` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | Pre-filled from current persisted value. |
| Stage 2 — *Extra fields* — *Vault* (visible only when `VoiceNote.type` = `obsidian`) | input (optional) | `VoiceNote.metadata.vault` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | Pre-filled from current persisted value. |
| Stage 2 — *Extra fields* — *Deadline* (visible only when `VoiceNote.type` = `todo`) | input (optional) | `VoiceNote.metadata.deadline` (slot per [D2.7](D2-datentypen.md#d27-typespecificdata)) | Pre-filled from current persisted value. |

The *Create Ticket* control inside the *Sent to GitHub* stage card is rendered but disabled while editing is in progress.

##### GUI Dynamik — state *editing*

**Save**

- **Trigger:** Click on the *Save* button in the *Processed* stage card.
- **Preconditions:** *Title* and *Body* are non-empty; type-specific extra fields satisfy their declared schema per [D2.7](D2-datentypen.md#d27-typespecificdata). Validation violations surface inline per *Form validation feedback* ([§B1.4.6](#b146-form-validation-feedback)).
- **Effect:** Submits the edited content ([UC-07](F2-anwendungsfaelle.md#uc-07--edit-generated-content)). Markdown is sanitised before persistence per [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) per [NFR-15b-04](N1-nichtfunktional.md). On success persists the new values to `VoiceNote.processedTitle`, `VoiceNote.processedBody`, and the affected `VoiceNote.metadata` slots; `VoiceNote.status` does not change; re-renders the dialogue in state `processed`. On failure stays in state *editing* with the failure surfaced inline.

**Cancel**

- **Trigger:** Click on the *Cancel* link in the *Processed* stage card.
- **Preconditions:** None.
- **Effect:** Discards the in-flight edits and returns the dialogue to state `processed`. Persisted values are not touched.

##### GUI Statik — state `sent`

The *Recorded* and *Processed* stage cards remain present and unchanged (the *Edit* control inside the *Processed* card is no longer offered — see *GUI Dynamik* below). Additionally:

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Stage 3 — issue reference (rendered as `Issue #<n>` and a link) | display | `VoiceNote.githubIssueNumber` and `VoiceNote.githubIssueUrl` | — |

##### GUI Dynamik — state `sent`

No forward actions are offered in this state. The note's content stages are read-only — the *Edit* control from state `processed` is not rendered, since the dispatched issue at GitHub is not under Herold's lifecycle ([NG-03](P1-ziele-rahmenbedingungen.md) *Local ticket lifecycle*).

**Open dispatched issue**

- **Trigger:** Click on the issue-reference link in the *Sent to GitHub* stage card.
- **Preconditions:** None.
- **Effect:** Opens `VoiceNote.githubIssueUrl` in a new tab. Herold itself does not navigate.

The shared *Back to Notes*, *Stream the audio recording*, and *Delete Note* actions remain available unchanged.

### B1.3.5 Configuration

#### DLG-10 — Settings

![DLG-10 — Settings](screenshots/dlg-10-settings.png)

| Section | Content |
|---------|---------|
| **Identifier** | DLG-10 |
| **Name** | Settings |
| **Realises** | [UC-12](F2-anwendungsfaelle.md#uc-12--view-settings); offers a shortcut to [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out). |
| **Purpose** | Let the operator inspect the active read-only system information — GitHub dispatch target, authentication state, and product identification — and offer an explicit sign-out next to it. |
| **Entry points** | *Settings* item in the navigation chrome. |
| **Layout regions** | Navigation chrome; page header; *GitHub* region with the dispatch target (owner and repository, rendered with non-secret characters partially masked); *Authentication* region with the second-factor and API-key state; *About* region with product name and version; sign-out action. |
| **Outcomes** | *Logout:* session invalidated; route to DLG-01.<br>*No other state change is possible from this screen* — configuration changes happen out-of-band on the host (see P1 constraints). |
| **Validation** | Not applicable. |
| **Empty / loading / error states** | None expected for the page itself; the displayed values are static configuration plus per-account state. |
| **Qualities** | *Application chrome* ([§B1.4.1](#b141-application-chrome)); read-only nature aligns with the P1 constraint that configuration is host-managed. |

##### GUI Statik

| Field | Kind | Mapping | Default |
|-------|------|---------|---------|
| Page title | display | — | "Settings". |
| Page subtitle | display | — | "Configuration and account management." |
| GitHub *Owner* | display | `GitHubRepository.owner`, partially masked (only the leading and trailing characters of the configured value are rendered, the middle is replaced by `*` characters) | From host configuration ([UC-12](F2-anwendungsfaelle.md#uc-12--view-settings)). |
| GitHub *Repo* | display | `GitHubRepository.name`, partially masked under the same rule | From host configuration. |
| Authentication — *TOTP Confirmed* | display | Derived from `Operator.totpConfirmedAt` (set ⇒ confirmed; unset ⇒ not confirmed) | Live value. |
| Authentication — *API Key Active* | display | Derived from `Operator.apiKeyHash` (present ⇒ active; absent ⇒ inactive) | Live value. |
| About — product name | display | — | "Herold — Voice Dispatch System". |
| About — version | display | — | Build version of the running Herold instance ([UC-12](F2-anwendungsfaelle.md#uc-12--view-settings)). |

##### GUI Dynamik

**Logout**

- **Trigger:** Click on the *Logout* button (or the *Sign out* control in the persistent navigation chrome — see [§B1.4.1](#b141-application-chrome)).
- **Preconditions:** None.
- **Effect:** Triggers [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out). The session is invalidated and the dialogue routes to DLG-01.

---

## B1.4 Cross-cutting Dialogue Patterns

Patterns reused across multiple screens. Each subsection describes the pattern once; the per-screen specifications in B1.3 only reference the subsection by name.

### B1.4.1 Application chrome

Every authenticated screen (DLG-06 to DLG-10) is wrapped in the same persistent navigation surface offering the four primary destinations — *Dashboard* (DLG-06), *Record* (DLG-09), *Notes* (DLG-07), *Settings* (DLG-10) — plus a *Sign out* control that realises [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out). The control's position is layout-adaptive (a persistent side rail on the desktop form factor and a bottom-edge bar on mobile per [§B1.4.9](#b149-mobile-usage)), but its inventory is invariant. The chrome is rendered identically across all authenticated screens; the active destination is highlighted. The chrome is absent on every screen in [§B1.3.1](#b131-access) (Access), since no session is established yet.

*Desktop chrome — persistent side rail with brand, four destinations, and a sign-out anchored at the bottom:*

![Desktop chrome — side rail](screenshots/pattern-desktop-chrome.png)

*Mobile chrome — same destinations rendered as a bottom-edge bar; the brand becomes a header element:*

![Mobile chrome — bottom-edge bar](screenshots/pattern-mobile-chrome.png)

### B1.4.2 Unauthenticated redirect

When the operator addresses any authenticated screen (DLG-06 to DLG-10) without an active session — for example after the session has been invalidated by [UC-04](F2-anwendungsfaelle.md#uc-04--sign-out) or has otherwise expired — the system routes them to DLG-01 instead of rendering the requested screen. No partial content of the requested screen is shown. After a successful sign-in the operator lands on DLG-06; the originally requested screen is *not* re-resolved post-login (deep-link preservation is out of scope).

### B1.4.3 Synchronous operation feedback

Operations that block the HTTP request per [NFR-12a-01](N1-nichtfunktional.md) *Synchronous Processing* — *Process Note* ([UC-06](F2-anwendungsfaelle.md#uc-06--process-voice-note)), *Create Ticket* ([UC-08](F2-anwendungsfaelle.md#uc-08--dispatch-voice-note)), and the credential-verification actions in DLG-01/02/03/04 — replace their triggering control with a non-interactive progress indicator and disable any other action that would interfere with the in-flight request. The loading affordance is local to the affected stage or form region; the rest of the screen remains visible so the operator keeps context. The expected duration is the ~10–30 s window stated in [NFR-12a-01](N1-nichtfunktional.md). The pattern does not include an explicit cancel control, since the request cannot be cancelled meaningfully at the HTTP level.

*Example — DLG-08 during *Process Note*: the *Processed* stage card is replaced with a progress affordance while the synchronous request is in flight; navigation chrome and surrounding stages remain visible:*

![Synchronous operation feedback (DLG-08 processing)](screenshots/dlg-08-note-detail-processing.png)

### B1.4.4 Synchronous error handling

When an action governed by [NFR-12d-01](N1-nichtfunktional.md) *Synchronous Error Handling* fails, the system surfaces the failure inline next to the triggering control (not as a transient toast) and keeps the operator on the same screen. The note's status does *not* advance, the failure reason is preserved against the note, and the original action remains available so the operator can retry deliberately — no automatic retry. The wording is the failure reason produced by the underlying operation; in the recovery branch ([UC-03](F2-anwendungsfaelle.md#uc-03--recover-access)) it is replaced by a single generic rejection per [NFR-15a-04](N1-nichtfunktional.md) *Recovery Token Expiry*, which deliberately conflates the three failure modes.

*Example — DLG-08 after a failed processing attempt: the failure reason is shown inline, status remains `recorded`, and the *Process Note* control is offered again so the operator can retry:*

![Synchronous error handling (DLG-08 error)](screenshots/dlg-08-note-detail-error.png)

### B1.4.5 Confirmation modals

Irreversible actions — currently only *Delete Note* ([UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note)) — open a confirmation prompt that names the affected note, restates the irreversible nature of the action, and offers two clearly distinct controls: *Confirm* and *Cancel*. The destructive *Confirm* is visually de-emphasised relative to *Cancel* so it cannot be triggered by reflex. The prompt blocks interaction with the underlying screen until resolved. *Cancel* leaves the underlying screen untouched; *Confirm* runs the synchronous deletion and, on success, navigates away from DLG-08 to DLG-07.

*Example — confirmation modal opened from DLG-08 for [UC-11](F2-anwendungsfaelle.md#uc-11--delete-a-voice-note); the underlying screen is dimmed but visible so the operator keeps context:*

![Confirmation modal (Delete Note)](screenshots/pattern-confirm-modal.png)

### B1.4.6 Form validation feedback

Dialogue-level validation messages render inline next to the offending field (not in a global banner) and appear on submit; they do not pre-empt the operator while they are still typing. Algorithmic validation lives in [N2](N2-querschnittskonzepte.md) *Validation* and is run server-side; B1 only specifies *where* its outcome surfaces, not *how* it is computed. For the editing variant of DLG-08 the type-specific extra fields are validated against their declared schema in [D2.7](D2-datentypen.md#d27-typespecificdata); each violating field carries its own message, the form is not submitted, and the *Save* control remains available so the operator can correct and retry.

*Example — DLG-08 editing variant for a *YouTube Transcription* note: the source-URL field has a per-field violation message ("Must be a valid `http(s)` URL"); other fields are unaffected; the *Save* control remains visible:*

![Form validation feedback (DLG-08 editing)](screenshots/dlg-08-note-detail-editing.png)

### B1.4.7 Empty states

Where a list-shaped region has no content yet — the recent-notes region of DLG-06, the list region of DLG-07 ([UC-09](F2-anwendungsfaelle.md#uc-09--browse-voice-notes)) — the screen replaces the list with a short message naming what is missing and an inline action that bootstraps the missing content (typically *Capture a note*, routing to DLG-09). The empty state is not an error: no error styling, no retry. A list emptied by a filter (DLG-07) uses the same pattern but explains the filter combination instead, and the bootstrap action is *Clear filter*.

*Example — initial empty state on DLG-07: explains what is missing and offers the bootstrap action:*

![Empty state — no notes yet](screenshots/pattern-empty-list.png)

*Example — filter-induced empty state on DLG-07: filter selections stay visible, the empty body cites the active combination, and the bootstrap action is *Clear filter*:*

![Empty state — filter has no matches](screenshots/pattern-empty-filtered.png)

### B1.4.8 One-time secret display

A value that is generated by the system and shown to the operator exactly once, never again retrievable, is rendered with three reinforcing cues: a visually distinct container (DLG-03 uses scannable provisioning material; DLG-05 uses the second-factor-coloured frame), a copy affordance, and an explicit warning that the value will not be shown again. Leaving the screen forfeits the value irrecoverably; in the recovery flow this means the operator must restart [UC-03](F2-anwendungsfaelle.md#uc-03--recover-access) with a freshly placed token. The pattern does not offer a "show again" control.

The two screens that apply this pattern are DLG-03 (TOTP secret in scannable form, see [§B1.3.1](#b131-access)) and DLG-05 (new API key with copy affordance and warning, see [§B1.3.1](#b131-access)).

### B1.4.9 Mobile usage

Per [NFR-13a-01](N1-nichtfunktional.md) *Mobile Usage on the Go*, every screen is usable on a current mobile browser (Safari iOS, Chrome Android). The application chrome ([§B1.4.1](#b141-application-chrome)) collapses from a side rail to a bottom-edge bar; primary actions (notably *Record* on DLG-09 and *Save* on DLG-08's editing variant) remain reachable with a single thumb in portrait orientation. DLG-09 specifically is laid out so that the record/stop control is the dominant target while capture is in progress, since this is the use case most often invoked in motion.

*Example — DLG-09 in mobile portrait: the record control is the dominant target; navigation collapses to the bottom edge:*

![Mobile usage (DLG-09 idle)](screenshots/pattern-mobile-recording.png)

---

## B1.5 Out of Scope for B1

- **Visual design language.** Colours, typography, glow, spacing — fixed centrally in `DESIGN.md`, not duplicated per screen.
- **Pixel-level layout, component library, or front-end framework.** Those are implementation choices; B1 stays at the level of regions and controls.
- **Internal screen-to-screen URLs and route names.** Implementation-bound; documented in code and architecture.
- **Algorithmic validation and sanitisation.** Lives in [N2](N2-querschnittskonzepte.md) *Validation* (per-type input) and [F3.AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation) *Markdown Sanitisation*; B1 only cites where it is surfaced.
- **Localisation and translation.** Herold runs in a single language for a single operator; multi-language support is not in scope.
- **Accessibility conformance levels.** *TBD* — defer to N1 once a conformance target is fixed.

---

## B1.6 Cross-references

| Block | Relevance to B1 |
|-------|-----------------|
| [F2](F2-anwendungsfaelle.md) | Every screen in B1.3 realises one or more use cases; the *Realises* line in each per-screen table cites the UC and scenario step. |
| [F3](F3-anwendungsfunktionen.md) | Sanitisation surfaced in B1 is implemented by [AF-03](F3-anwendungsfunktionen.md#af-03--markdown-sanitisation). |
| [N1](N1-nichtfunktional.md) | Synchronous-processing feedback ([NFR-12a-01](N1-nichtfunktional.md)), error handling ([NFR-12d-01](N1-nichtfunktional.md)), rate limiting and lockout ([NFR-15a-02](N1-nichtfunktional.md)), audio upload constraints ([NFR-15a-03](N1-nichtfunktional.md)), recovery token expiry ([NFR-15a-04](N1-nichtfunktional.md)), mobile usability ([NFR-13a-01](N1-nichtfunktional.md)). |
| [N2](N2-querschnittskonzepte.md) | *Validation* underpins B1 form-validation feedback; authentication and session handling underpin DLG-01 to DLG-05 and the cross-cutting patterns in B1.4. |
| `DESIGN.md` | Visual identity. B1 deliberately abstracts from it. |
| [E2](E2-glossar.md) | Definitions for *message type*, *voice note*, *Recovery*. |
