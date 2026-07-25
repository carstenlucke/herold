# 8 Cross-cutting Concepts

This chapter documents the concepts that span multiple building blocks. The *strategies* are fixed, implementation-free, at spec level in [`N2 — Cross-cutting Concepts`](../spec/N2-querschnittskonzepte.md); each section here records how the strategy is **realised in code** — classes, config keys, algorithms — and names deviations where the implementation differs from the spec (consolidated as debt [D-07](A11-risks-and-technical-debts.md#112-technical-debts)). Section 8.1 has no N2 counterpart; it is an architecture-native concept (persistence mapping). Where a concept leans on a Laravel mechanism, the mechanism links to the framework documentation pinned to the version in use — [13.x](https://laravel.com/docs/13.x), matching `laravel/framework` v13.4 in `composer.lock`.

| § | Concept | Spec strategy |
|---|---------|---------------|
| [8.1](#81-domain-model-and-persistence) | Domain model and persistence | [D1](../spec/D1-datenmodell.md), [D2](../spec/D2-datentypen.md) |
| [8.2](#82-type-driven-configuration) | Type-driven configuration | [N2.2](../spec/N2-querschnittskonzepte.md#n22-type-driven-configuration) |
| [8.3](#83-validation) | Validation | [N2.3](../spec/N2-querschnittskonzepte.md#n23-validation) |
| [8.3.1](#831-recipes-per-boundary) | ↳ Recipes per boundary | |
| [8.4](#84-authentication-and-session) | Authentication and session | [N2.4](../spec/N2-querschnittskonzepte.md#n24-authentication-and-session) |
| [8.4.1](#841-walkthrough--the-two-factor-handshake) | ↳ Walkthrough — the two-factor handshake | |
| [8.5](#85-content-sanitisation) | Content sanitisation | [N2.7](../spec/N2-querschnittskonzepte.md#n27-content-sanitisation) |
| [8.5.1](#851-walkthrough--the-gate-in-code) | ↳ Walkthrough — the gate in code | |
| [8.6](#86-failure-handling) | Failure handling | [N2.5](../spec/N2-querschnittskonzepte.md#n25-failure-handling) |
| [8.6.1](#861-walkthrough--a-re-runnable-pipeline-action) | ↳ Walkthrough — a re-runnable pipeline action | |
| [8.7](#87-logging) | Logging | [N2.6](../spec/N2-querschnittskonzepte.md#n26-logging) |
| [8.7.1](#871-walkthrough--redaction-that-call-sites-cannot-bypass) | ↳ Walkthrough — redaction that call sites cannot bypass | |
| [8.8](#88-secret-handling) | Secret handling | [N2.8](../spec/N2-querschnittskonzepte.md#n28-secret-handling) |
| [8.8.1](#881-walkthrough--the-four-guards-on-a-secret) | ↳ Walkthrough — the four guards on a secret | |

---

## 8.1 Domain model and persistence

The physical schema is the SQLite realisation of the D1 information model:

![Herold — relational data model (SQLite)](diagrams-png/relational-datamodel.png)

*Source: [`diagrams/relational-datamodel.plantuml`](diagrams/relational-datamodel.plantuml). Kept in sync with every schema change ([CONV-06](A02-architecture-constraints.md#23-conventions)).*

**Table-to-D1 mapping.**

| D1 entity | Physical realisation |
|-----------|----------------------|
| [`VoiceNote`](../spec/D1-datenmodell.md#voicenote) | Table `voice_notes`; model `App\Models\VoiceNote` with [`HasUlids`](https://laravel.com/docs/13.x/eloquent#uuid-and-ulid-keys) — the ULID primary key delivers the time-sortable `Identifier` of [D2.2](../spec/D2-datentypen.md#d22-identifier) (recency ordering in UC-09 is `latest()` on `created_at`). `metadata` is a JSON column cast to `array`, holding the snake_case slot record per type (`youtube_url`, `entry_date`, `vault`, `deadline`). `status` is a string column cast to the `App\Enums\NoteStatus` backed enum. |
| [`Operator`](../spec/D1-datenmodell.md#operator) | Table `users`; model `App\Models\User`. Herold-specific columns `api_key_hash` (SHA-256 hex), `totp_secret` (Laravel [`encrypted` cast](https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting) — ciphertext at rest), `totp_confirmed_at`. The single-instance rule ([CON-3a-04](../spec/P1-constraints.md#con-3a-04-single-user-system)) is enforced twice: in code via `User::sole()` and in the database via the SQLite trigger `enforce_users_singleton` (`BEFORE INSERT … RAISE(ABORT)`), so even raw SQL cannot create a second operator. |
| [`RecoveryToken`](../spec/D1-datenmodell.md#recoverytoken) | Deliberately **not** a table: the file `.herold-recovery` on the `local` disk. `token` = file content, `placedAt` = file mtime ([§ 6.5](A06-runtime-view.md#65-recover-access)). |
| [`GitHubIssue` / `GitHubRepository`](../spec/D1-datenmodell.md#d12-ticket-data) | Not persisted — owned by GitHub. Herold stores only the back-references `github_issue_number` and `github_issue_url` on `voice_notes`. Repository coordinates come from configuration, not data. |

**Framework remnants.** `users.password` (seeded with an inert bcrypt value), `remember_token`, `email_verified_at`, and the stock `password_reset_tokens`, `cache*`, `jobs*` tables exist as Laravel scaffolding but participate in no Herold flow (debt [D-02](A11-risks-and-technical-debts.md#112-technical-debts)). Sessions are first-class, though: the `sessions` table backs the session guard of § 8.4.

**[Migrations](https://laravel.com/docs/13.x/migrations#running-migrations)** run out-of-band (`php artisan migrate --force`) — automatically at container start in dev, manually via SSH in production ([§ 7.1.2](A07-deployment-view.md#712-development--docker-compose), [ORG-06](A02-architecture-constraints.md#22-organisational-constraints)). The initial migration set also *seeds* the singleton operator row from `HEROLD_API_KEY` ([S3.4](../spec/S3-inbetriebnahme.md#s34-first-commissioning) step 5).

## 8.2 Type-driven configuration

The single resolution point of [N2.2](../spec/N2-querschnittskonzepte.md#n22-type-driven-configuration) is **`config/herold.php`**, key `types`. One entry per `MessageTypeDT` value:

```php
'todo' => [
    'label' => '…', 'icon' => 'mdi-…',          // display attributes (browser-safe)
    'github_label' => 'type:todo',               // dispatch label
    'needs_current_date_context' => true,        // inject current date into the prompt
    'extra_fields' => [                          // D2.7 slot inventory
        ['name' => 'deadline', 'type' => 'date', 'required' => false, 'label' => '…'],
    ],
    'preprocessing_prompt' => '…',               // server-only (NFR-15b-02)
],
```

Consumers and their access paths:

| Consumer | Uses |
|----------|------|
| `MessageTypeRegistry` | Browser-safe projection: `all()` strips `preprocessing_prompt` and `needs_current_date_context` before types reach Inertia props or `GET /types`. |
| `StoreVoiceNoteRequest` / `VoiceNoteController::update` | `type` validated against `array_keys(config('herold.types'))`; per-type `extra_fields` become validation rules (`url` → `url`, `date` → `date_format:Y-m-d`, else `string`). |
| `PreprocessingService` | `preprocessing_prompt`, `extra_fields` (extraction schema), `needs_current_date_context`. |
| `IssueContentSanitizer` | `extra_fields` labels for the `## Metadata` section. |
| `VoiceNoteController::send` | `github_label` → the issue's single label. |

No consumer hard-codes a type name.

> **Recipe — add a new message type.** Add one entry under `types` in `config/herold.php` with the keys shown above — nothing else. Every consumer in the table resolves the new type at runtime: it appears in the capture UI (Inertia props / `GET /types`), its key and slots validate ([§ 8.3](#83-validation)), `PreprocessingService` uses its prompt, and dispatch applies its `github_label`. `TypeExtensionTest::test_new_type_can_be_added_via_config` asserts exactly this path with a type that exists only in config.

**Layering note:** the spec splits type definition into a code-level catalogue ([D2.4](../spec/D2-datentypen.md#d24-messagetypedt)) and host-level bindings ([NFR-14a-01](../spec/N1-nichtfunktional.md#14a-maintenance-requirements)); the implementation folds both layers into the config file — the catalogue *is* the set of config keys, which satisfies the fit criterion (bindings changeable without code edits) while making the "closed set" a convention between spec and config rather than a PHP enum.

## 8.3 Validation

Realisation of [N2.3](../spec/N2-querschnittskonzepte.md#n23-validation) — strict, schema-driven, and layered along every boundary through which data enters the system. **The server is the single authority**: whatever the browser checks is convenience only and is re-enforced server-side.

| Boundary | Validator | Rules | On failure |
|----------|-----------|-------|------------|
| **Browser (convenience)** | `Recording/Create.vue` | Vuetify `:rules` mark required metadata slots inline; the `canSave` computed keeps *Save* disabled until an audio blob exists and every required slot is filled; date slots are picker-driven (read-only text field), so a malformed date cannot be typed. | Inline field hint / disabled button. Never authoritative. |
| **Operator input — capture** | `StoreVoiceNoteRequest` (`POST /notes`), a [form request](https://laravel.com/docs/13.x/validation#form-request-validation) | `audio`: required, `file`, `mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4`, max 25 600 KB (= the 25 MB of [NFR-15a-03](../spec/N1-nichtfunktional.md#15a-access-requirements)); `type`: `Rule::in` over the configured type keys; per-slot rules derived from the type's `extra_fields` (`url` → `url`, `date` → `date_format:Y-m-d`, else `string`; `required` flag honoured). `validated()` additionally intersects `metadata` against the declared slot names — unknown keys dropped, so nothing undeclared ever reaches the JSON column. | 422 field errors, surfaced inline by Inertia; nothing persisted. |
| **Operator input — edit** | `VoiceNoteController::update` (`PUT /notes/{note}`) | Free-text rules for `transcript`, `processed_title` (max 255), `processed_body`; identical per-slot rule construction and key-intersection filter, evaluated against the note's *bound* type. | 422, as above. |
| **Operator input — authentication** | `AuthController` | API key, TOTP code, and recovery secret are verified as credentials (`hash_equals`, RFC 6238 drift window) — see [§ 8.4](#84-authentication-and-session). | Uniform rejection (error flash / 404), throttled, logged with source IP. |
| **Machine input — AI responses** | `AIService`, `PreprocessingService` | Non-2xx responses from both OpenAI endpoints throw; `chat()` structurally validates the returned payload — it must decode to a JSON object carrying `title` **and** `body`; `PreprocessingService` rejects notes whose `type` has no config entry. | `RuntimeException` → the [§ 8.6](#86-failure-handling) path (`status = error`, inputs preserved). |
| **Configuration** | Adapter constructors | `AIService` / `GitHubService` fail fast when their API key/token is unset — see [§ 8.8](#88-secret-handling). | `RuntimeException` before any external call. |

[Rate limits](https://laravel.com/docs/13.x/routing#rate-limiting) complement validation at the same boundaries: `throttle:10,60` on capture (deviation from the spec's 20/h — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(c)), `throttle:5,1` on both sign-in factors, `throttle:5,60` on recovery. Validation rejections never touch `status`/`error_message` ([§ 8.6](#86-failure-handling)).

### 8.3.1 Recipes per boundary

Each row of the table above has one canonical shape in the codebase. The snippets below are abridged from the named files and serve as the template when a boundary of that kind is added.

**Browser (convenience).** A declarative Vuetify rule for the visible hint, plus a `canSave` guard that mirrors the same predicate for the submit button. The rules array is derived from the type projection, never hard-coded per field (`resources/js/Pages/Recording/Create.vue`):

```vue
<v-text-field :rules="field.required ? [rules.required] : []" />
```

```ts
const rules = { required: (v: string) => !!v || 'This field is required' }

const canSave = computed(() => {
  if (!audioBlob.value) return false
  for (const field of currentExtraFields.value) {
    if (field.required && !extraFieldValues.value[field.name]) return false
  }
  return true
})
```

Everything here is repeated server-side; a browser check may be relaxed or removed without weakening the system.

**Operator input — capture.** A [form request](https://laravel.com/docs/13.x/validation#form-request-validation) with two parts: static rules for the fixed fields, dynamic rules built from the type's `extra_fields`, and an overridden `validated()` that drops undeclared `metadata` keys (`app/Http/Requests/StoreVoiceNoteRequest.php`):

```php
public function rules(): array
{
    $rules = [
        'audio' => ['required', 'file', 'max:25600', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4'],
        'type' => ['required', 'string', Rule::in(array_keys(config('herold.types', [])))],
        'metadata' => ['nullable', 'array'],
    ];

    foreach (config("herold.types.{$this->input('type')}.extra_fields", []) as $field) {
        $rules["metadata.{$field['name']}"] = [
            $field['required'] ? 'required' : 'nullable',
            match ($field['type']) {
                'url' => 'url',
                'date' => 'date_format:Y-m-d',
                default => 'string',
            },
        ];
    }

    return $rules;
}

public function validated($key = null, $default = null): mixed
{
    $validated = parent::validated($key, $default);
    // … intersect $validated['metadata'] with the declared slot names (allow-list) …
    return $validated;
}
```

Two invariants make this the template: rules are *derived from config*, and the allow-list intersection is applied **after** validation, so an undeclared key that happens to pass its rules still never reaches the JSON column.

**Operator input — edit.** Same construction, but inline in the controller and against the note's *bound* type rather than a submitted one — the type is immutable after capture and must not be re-derived from request input (`VoiceNoteController::update`):

```php
$rules = [
    'transcript' => 'nullable|string',
    'processed_title' => 'nullable|string|max:255',
    'processed_body' => 'nullable|string',
    'metadata' => 'nullable|array',
];

foreach (config("herold.types.{$note->type}.extra_fields", []) as $field) {
    $rules["metadata.{$field['name']}"] = [ /* … as above … */ ];
    $allowedKeys[] = $field['name'];
}

$validated = $request->validate($rules);
$validated['metadata'] = array_intersect_key($validated['metadata'] ?? [], array_flip($allowedKeys)) ?: null;
```

**Operator input — authentication.** Shape validation (`required|string`) is only the pre-filter; the actual check is a credential comparison in constant time, and the failure path is *uniform, logged, and non-informative* — the same message regardless of which part was wrong (`AuthController::verifyKey`):

```php
$request->validate(['api_key' => 'required|string']);

if (! hash_equals($user->api_key_hash, hash('sha256', $request->input('api_key')))) {
    Log::warning('Failed API key verification attempt.', ['ip' => $request->ip()]);

    return back()->withErrors(['api_key' => 'Invalid API key.']);
}
```

Never use `==`/`===` on a secret, never distinguish "unknown user" from "wrong key" in the response, and always log the attempt with the source IP ([§ 8.7](#87-logging)). Recovery follows the same shape but collapses every rejection to `abort(404)`.

**Machine input — AI responses.** An external response is untrusted input: check the transport status *and* the payload structure before the value is used, and fail with an exception rather than a partially-filled model (`AIService::chat`):

```php
if ($response->failed()) {
    throw new RuntimeException("Chat completion failed: {$response->status()} — {$response->body()}");
}

$parsed = json_decode($response->json('choices.0.message.content'), true);

if (! is_array($parsed) || ! isset($parsed['title'], $parsed['body'])) {
    throw new RuntimeException('Chat response did not contain expected "title" and "body" keys.');
}

return $parsed;
```

The thrown exception is caught once per pipeline action and turned into `status = error` with the inputs preserved ([§ 8.6](#86-failure-handling)) — the boundary validator itself never writes state.

**Configuration.** Adapters validate their own configuration in the constructor, so a misconfigured deployment fails before the first outbound call instead of mid-pipeline (`GitHubService::__construct`, likewise `AIService`):

```php
$config = config('herold.github');

$this->token = $config['token'] ?? throw new RuntimeException('GitHub token is not configured.');
$this->owner = $config['owner'] ?? throw new RuntimeException('GitHub owner is not configured.');
$this->repo  = $config['repo']  ?? throw new RuntimeException('GitHub repo is not configured.');
```

The message names the missing key but never the value ([§ 8.8](#88-secret-handling)).

> **Recipe — add a validated metadata slot.** Append one entry to the type's `extra_fields` in `config/herold.php`:
>
> ```php
> ['name' => 'deadline', 'type' => 'date', 'required' => false, 'label' => 'Deadline'],
> ```
>
> This single entry drives every layer of the table above: the rendered form control including its client-side required rule (`Recording/Create.vue` builds the form from the browser-safe type projection), the server rules on capture **and** edit, the key-intersection filter, the extraction schema (`PreprocessingService` merges an AI-extracted value of the same name into `metadata`), and the labelled row in the issue's `## Metadata` section ([§ 8.5](#85-content-sanitisation)). No other file changes.

## 8.4 Authentication and session

Realisation of [N2.4](../spec/N2-querschnittskonzepte.md#n24-authentication-and-session), concentrated in `AuthController` ([§ 6.4](A06-runtime-view.md#64-sign-in-with-inline-totp-enrolment)):

- **Factor 1 — API key.** Presented at `POST /login/key`, hashed with SHA-256 and compared via `hash_equals` against `users.api_key_hash`. Success is held as the session flag `auth.key_verified` — not yet a login.
- **Factor 2 — TOTP.** Home-rolled RFC 6238/4226 ([TECH-13](A02-architecture-constraints.md#21-technical-constraints)), no library: 160-bit secret, custom Base32 codec, HMAC-SHA1 with dynamic truncation to 6 digits, 30 s steps, ±1 step clock-drift window, `hash_equals` comparison. Enrolment is inline on first sign-in (secret auto-generated after factor 1; `otpauth://` provisioning URI rendered as a QR client-side via the `qrcode` package; confirmed code sets `totp_confirmed_at`).
- **Session as the gate.** Only after factor 2 does `Auth::login()` run, followed by [session-id regeneration](https://laravel.com/docs/13.x/session#regenerating-the-session-id) and removal of the intermediate flags. Every protected route sits behind the stock session [`auth` middleware](https://laravel.com/docs/13.x/authentication#protecting-routes) — the single gate; no per-use-case checks exist. `POST /logout` invalidates the session ([UC-04](../spec/F2-anwendungsfaelle.md#uc-04--sign-out)).
- **Defence-in-depth at the edge.** `SecurityHeaders` middleware on the whole `web` group: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(self)`.
- **Recovery** replaces both factors via the out-of-band file token ([§ 6.5](A06-runtime-view.md#65-recover-access)); all rejection reasons collapse to a uniform 404 ([NFR-15a-04](../spec/N1-nichtfunktional.md#15a-access-requirements)).
- **Authorisation is degenerate** by design: one operator, one role, full access ([N2.9](../spec/N2-querschnittskonzepte.md#n29-out-of-scope-for-n2)).

### 8.4.1 Walkthrough — the two-factor handshake

**Step 1 — a valid API key advances the sign-in flow but does not log the operator in.** After comparing the key in constant time ([§ 8.3.1](#831-recipes-per-boundary)), `AuthController::verifyKey` stores two temporary values in the session:

```php
$request->session()->put('auth.key_verified', true);
$request->session()->put('auth.user_id', $user->id);
```

These values record that factor 1 succeeded and identify the operator for the next step. Laravel still considers the session unauthenticated, so protected routes remain inaccessible. The values only allow the operator to continue with TOTP setup or verification.

**Step 2 — a TOTP code is accepted only after the API key.** Both endpoints that accept a TOTP code — `verifyTotp` for a returning operator and `confirmTotp` for first-time enrolment — first check the temporary session values from step 1:

```php
$request->validate(['totp_code' => 'required|string|size:6']);

if (! $request->session()->get('auth.key_verified')) {
    return redirect()->route('login');           // factor 1 not passed — no error detail
}

$user = User::findOrFail($request->session()->get('auth.user_id'));
```

Without `auth.key_verified`, the request returns to the login page. A direct call to a TOTP endpoint therefore cannot skip the API-key step. The user id also ensures that the code is checked for the same operator whose API key was accepted.

**Step 3 — the TOTP code is checked against the current time window.** A TOTP code changes every 30 seconds. To tolerate small clock differences between Herold and the authenticator app, `verifyTotpCode` accepts the expected code for the previous, current, or next 30-second interval. Each comparison uses `hash_equals` to avoid timing leaks:

```php
$currentTimestamp = (int) floor(time() / 30);       // the RFC 6238 time step

for ($offset = -1; $offset <= 1; $offset++) {       // ±1 step tolerates clock drift
    if (hash_equals($this->generateTotpCode($secret, $currentTimestamp + $offset), $code)) {
        return true;
    }
}

return false;
```

`generateTotpCode` implements the underlying RFC 4226 calculation without a library (`TECH-13`): it encodes the time counter as eight bytes, calculates HMAC-SHA1 with the shared secret, and derives a six-digit code from the result:

```php
$counterBytes = pack('N*', 0, $counter);                        // 8-byte big-endian counter
$hash = hash_hmac('sha1', $counterBytes, $this->base32Decode($base32Secret), true);

$offset = ord($hash[19]) & 0x0F;                                // dynamic truncation
$binary = ((ord($hash[$offset]) & 0x7F) << 24)
    | ((ord($hash[$offset + 1]) & 0xFF) << 16)
    | ((ord($hash[$offset + 2]) & 0xFF) << 8)
    | (ord($hash[$offset + 3]) & 0xFF);

return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
```

One verification attempt therefore checks at most three candidate codes. For returning operators, `POST /login/totp` is limited to five requests per minute. The first-time enrolment endpoint `POST /login/totp/confirm` currently has no equivalent route-level throttle.

**Step 4 — a valid TOTP code completes the login.** Only now does Laravel mark the operator as authenticated. The controller then replaces the session id to prevent session-fixation attacks and removes the temporary values from step 1:

```php
Auth::login($user);
$request->session()->regenerate();
$request->session()->forget(['auth.key_verified', 'auth.user_id']);

return redirect()->intended(route('dashboard'));
```

During first-time enrolment, `confirmTotp` first records `$user->update(['totp_confirmed_at' => now()])`; the same request then completes the login, so the operator does not need to enter another TOTP code. Signing out removes the authentication state, invalidates all data in the current session, and rotates the CSRF token:

```php
Auth::logout();
$request->session()->invalidate();
$request->session()->regenerateToken();
```

**Step 5 — authentication is enforced centrally for all protected routes.** The `auth` middleware is attached once to the protected route group in `routes/web.php`. Laravel therefore checks the session before calling any route in that group. Controllers and use cases do not repeat this check, so the access rule is easy to find and cannot be forgotten in an individual controller:

```php
Route::middleware('auth')->group(function () {
    Route::post('/notes', [VoiceNoteController::class, 'store'])->middleware('throttle:10,60');
    // … every protected route …
});
```

**Step 6 — every web response receives the same browser security headers.** `SecurityHeaders` is registered once for Laravel's entire `web` middleware group. After a controller has produced a response, the middleware adds these headers before returning it to the browser:

```php
$response = $next($request);

$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'camera=(), microphone=(self)');

return $response;
```

The headers prevent embedding Herold in a frame, MIME-type guessing, and unnecessary referrer disclosure. They also disable camera access. `microphone=(self)` still allows Herold's own audio-capture UI to use the microphone ([§ 8.9](#89-audio-capture-and-storage)).

**Not set: `Content-Security-Policy`.** The list above is complete — there is no CSP, and therefore no browser-side fallback if script injection ever became possible. The current exposure is theoretical (no `v-html`/`innerHTML` exists in `resources/js/`), but the gap is recorded as debt [D-08](A11-risks-and-technical-debts.md#112-technical-debts) because a markdown preview of the raw `processed_body` would change that assessment in a single commit.

**Step 7 — recovery uses a one-time file token and never reveals why an attempt failed.** Recovery replaces the API key and TOTP setup, so it must work without either normal sign-in factor. `processRecovery` checks that the recovery file exists, is no more than 60 minutes old, and contains the submitted token. Each failure has a distinct internal log entry, but the client always receives the same 404 response. An attacker therefore cannot tell whether no recovery is active, the token expired, or the submitted value was wrong:

```php
if (! Storage::disk('local')->exists($recoveryPath)) {
    Log::warning('Recovery attempt with no recovery file.', ['ip' => $request->ip()]);
    abort(404);
}

if (now()->timestamp - Storage::disk('local')->lastModified($recoveryPath) > 60 * 60) {
    Storage::disk('local')->delete($recoveryPath);   // expired: remove, don't just reject
    Log::warning('Recovery attempt with expired file.', ['ip' => $request->ip()]);
    abort(404);
}

if (! hash_equals(trim(Storage::disk('local')->get($recoveryPath)), trim($request->input('token')))) {
    Log::warning('Recovery attempt with invalid token.', ['ip' => $request->ip()]);
    abort(404);
}
```

On success, the token file is deleted before the credentials are changed, preventing the token from being used again. Herold generates a new API key, stores only its SHA-256 hash, and clears the TOTP configuration so that it must be enrolled again:

```php
Storage::disk('local')->delete($recoveryPath);

$newApiKey = Str::random(64);

$user->update([
    'api_key_hash' => hash('sha256', $newApiKey),
    'totp_secret' => null,                    // both factors reset: re-enrolment on next sign-in
    'totp_confirmed_at' => null,
]);

return Inertia::render('Auth/RecoverySuccess', ['apiKey' => $newApiKey]);
```

The clear-text API key is shown only in this success response. Recovery does not create an authenticated session; the operator signs in again with the new key and enrols TOTP during that sign-in.

## 8.5 Content sanitisation

Sanitisation is deliberately a **single, late gate** in the note lifecycle: nothing is filtered on the way *in*, everything relevant is filtered at the one point where content leaves Herold for a surface that others read. Stage by stage, where untrusted content crosses a boundary:

| Lifecycle stage | Content crossing a boundary | Sanitisation involvement |
|-----------------|-----------------------------|--------------------------|
| **Capture** (→ `recorded`) | Audio bytes and typed metadata slots enter. | None — inputs are validated and key-filtered ([§ 8.3](#83-validation)) but not content-filtered; a free-text slot may legally contain markup. Audio is opaque binary: never rendered as markup, only played back via the authenticated streaming route ([§ 8.9](#89-audio-capture-and-storage)). |
| **Process — transcription** | Audio leaves to the Transcription API (NB-02); a plain-text transcript returns. | None — the transcript is persisted verbatim. It is source material the operator must be able to review and correct; fidelity beats filtering here. |
| **Process — generation** (→ `processed`) | Transcript, metadata, and (per type) the current date leave to the Chat Completion API (NB-03); `title`, `body`, and extracted slot values return. | None in either direction. The outbound guards at this boundary are of a different kind: the system prompt comes exclusively from server-side config, and the response is forced into JSON mode and structurally validated ([§ 8.3](#83-validation)). The returned content is persisted **raw** — see the deviation below. |
| **Review & edit** (`processed`) | The operator edits transcript, title, body, metadata. | None — edits are validated ([§ 8.3](#83-validation)) and persisted raw. The operator deliberately reviews and edits the verbatim intermediate, not a filtered rendition of it. |
| **Dispatch** (→ `sent`) | Title, body, and metadata leave to GitHub (NB-04). | **The gate.** `IssueContentSanitizer::sanitizeAndWrap` sanitises `processed_body` and every metadata value individually; `processed_title` passes unaltered (GitHub renders titles as literal text, so the hidden-instruction carriers listed below cannot conceal themselves there). |

This placement is both safe and sufficient: upstream of dispatch, content never leaves the authenticated single-operator boundary, and Herold's own UI renders it exclusively through Vue text interpolation — no `v-html` exists in the codebase — so markup in a transcript or generated body is *displayed*, never *executed*. Dispatch is the first boundary where content reaches readers Herold does not control: GitHub's markdown rendering and, above all, the local AI agents (NB-05) that consume issues as work orders. And because `send` sanitises the note's *current* persisted state at dispatch time, edits can never route around the gate — whatever is dispatched has just passed through it.

The gate's algorithmic core ([AF-03](../spec/F3-anwendungsfunktionen.md#af-03--markdown-sanitisation)) lives in `IssueContentSanitizer`:

1. **Neutralise** — remove HTML comments (`<!-- … -->`, the classic hidden-instruction carrier), `<script>`/`<style>` blocks including content, every remaining HTML tag (`strip_tags`), `javascript:` URIs, and `data:text/html` payloads. Markdown structure (headings, lists, links, code fences) passes through untouched.
2. **Delimit** — compose the outbound body as sanitised `processed_body`, then a generated `## Metadata` section (labelled, individually sanitised values from the type's `extra_fields`), the sections separated by `---` rules — the visual boundary between operator-derived content and application-generated structure required by [NFR-15b-04](../spec/N1-nichtfunktional.md#15b-integrity-requirements).
3. **Exclude** — neither the transcript nor any prompt material is ever part of the issue body (asserted by `IssueSanitizationTest`, including the agent-directive injection case).

### 8.5.1 Walkthrough — the gate in code

**Step 1 — the entry point composes, it does not filter.** `sanitizeAndWrap` is the only public method; everything below it is private, so no caller can obtain half-sanitised output or reach the raw filter with the wrong input:

```php
public function sanitizeAndWrap(VoiceNote $note): string
{
    $sections = [$this->sanitize($note->processed_body ?? '')];

    if ($metadataSection = $this->buildMetadataSection($note)) {
        $sections[] = $metadataSection;
    }

    return implode("\n\n---\n\n", $sections);   // the NFR-15b-04 visual boundary
}
```

The `---` separator is not cosmetic: it is the required delimiter between operator-derived content and application-generated structure, which is what lets a reading agent tell the two apart.

**Step 2 — neutralise, in this order.** Each step removes one carrier class; the order matters, because `strip_tags` alone would *unwrap* a `<script>` block and leave its body as text:

```php
private function sanitize(string $content): string
{
    $content = preg_replace('/<!--.*?-->/s', '', $content);                     // 1. hidden-instruction carrier
    $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);  // 2. tag *and* content
    $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);    // 3. tag *and* content
    $content = strip_tags($content);                                            // 4. every remaining tag
    $content = preg_replace('/javascript\s*:/i', '', $content);                 // 5. active URI schemes
    $content = preg_replace('/data\s*:\s*text\/html/i', '', $content);

    return trim($content);
}
```

Note what is *not* touched: headings, lists, links, and code fences are markdown, not HTML, and pass through unchanged — the gate must not degrade the issue's readability for the human and agent readers it is written for.

**Step 3 — metadata is sanitised per value, never as a blob.** Labels come from the type's `extra_fields` ([§ 8.2](#82-type-driven-configuration)) and the value is filtered individually, so an injected directive inside a single slot cannot escape into the surrounding markdown structure:

```php
foreach ($note->metadata as $key => $value) {
    $label = /* … lookup $field['label'] for $key in extra_fields, fall back to $key … */;

    $lines[] = "- **{$label}:** {$this->sanitize((string) $value)}";
}

return "## Metadata\n\n".implode("\n", $lines);
```

**Step 4 — the caller passes the note, not a string.** `send` hands the *persisted* note to the sanitizer immediately before the external call, which is what makes the gate unbypassable by editing (`VoiceNoteController::send`):

```php
$body = $sanitizer->sanitizeAndWrap($note);      // current persisted state, sanitised here and only here

$result = $gitHubService->createIssue($note->processed_title ?? 'Untitled Voice Note', $body, $labels);
```

Two absences complete the picture: `$note->transcript` appears nowhere in this path (step 3 of the algorithm — *exclude*), and `processed_title` is passed through unfiltered by design, because GitHub renders issue titles as literal text.

**Deviation from N2.7:** the spec schedules sanitisation at *every* persistence and dispatch boundary; the implementation sanitises only the dispatched artefact — `processed_body` is persisted raw after generation ([UC-06](../spec/F2-anwendungsfaelle.md#uc-06--process-voice-note)) and after edits ([UC-07](../spec/F2-anwendungsfaelle.md#uc-07--edit-generated-content)). The dispatched issue is thereby fully covered; the persisted intermediate is not ([D-07](A11-risks-and-technical-debts.md#112-technical-debts)(b)).

## 8.6 Failure handling

Realisation of [N2.5](../spec/N2-querschnittskonzepte.md#n25-failure-handling) — synchronous, loss-free, operator-retried:

- **One catch per pipeline action.** `process` and `send` each wrap their work in a single `try/catch (\Throwable)`. The handler logs the failure (note id + message, through the redacting channel) and writes `status = NoteStatus::ERROR` plus `error_message`; the UI renders the message on the detail view.
- **Nothing is lost.** Audio file, transcript, and any generated content survive a failure; `process` clears `error_message` on entry and skips transcription when a transcript exists, so retries re-run only the failed remainder.
- **Retry asymmetry.** `process` has no status guard — it is both first run and retry. `send` admits only `status = processed`; after a failed dispatch (status `error`) the operator re-runs *Process* (which restores `processed`) and then *Send*. No speculative issue reference is ever stored on a mid-call failure ([§ 6.3](A06-runtime-view.md#63-dispatch-to-github)).
- **Realisation note.** The spec models failure as the orthogonal flag `errorMessage` with an unchanged three-value status ([D2.5](../spec/D2-datentypen.md#d25-notestatusdt)); the implementation adds `error` as a fourth `NoteStatus` case carrying the same information (visible as its own dashboard count). Semantically equivalent for retries, structurally different — [D-07](A11-risks-and-technical-debts.md#112-technical-debts)(a).
- **Validation rejections are not failures**: they surface as 422 field errors and never touch `status`/`error_message` ([§ 8.3](#83-validation)).

### 8.6.1 Walkthrough — a re-runnable pipeline action

**Step 1 — clear the previous failure, then work incrementally.** `process` opens by resetting `error_message` and skips any stage whose output already exists, which is what makes the *same* method serve as first run and as retry (`VoiceNoteController::process`):

```php
try {
    $note->update(['error_message' => null]);

    if ($note->audio_path && ! $note->transcript) {     // skip what a previous run already produced
        $transcript = $aiService->transcribe(Storage::disk('local')->path($note->audio_path));
        $note->update(['transcript' => $transcript]);
    }

    $preprocessingService->process($note);
```

A failure in generation therefore costs one Whisper call, not two: the transcript persisted by the earlier run survives and is reused.

**Step 2 — one catch, at the action boundary.** No inner `try` exists anywhere in the pipeline; every `RuntimeException` from validation ([§ 8.3](#83-validation)) or an adapter surfaces here:

```php
} catch (\Throwable $e) {
    Log::error('Voice note processing failed.', [
        'note_id' => $note->id,          // reference, never content — § 8.7
        'error' => $e->getMessage(),
    ]);

    $note->update([
        'status' => NoteStatus::ERROR,
        'error_message' => $e->getMessage(),
    ]);
}

return redirect()->route('notes.show', $note);   // same destination as success: the operator sees the state
```

`\Throwable`, not `\Exception`: a PHP `Error` must not escape as a 500, because that would leave the note in a stale status with no message for the operator.

**Step 3 — guard the transition, not the retry.** `send` differs from `process` in one respect only, and it is a precondition check *before* the `try`:

```php
if ($note->status !== NoteStatus::PROCESSED) {
    return redirect()->route('notes.show', $note)
        ->withErrors(['status' => 'Note must be processed before sending.']);
}
```

This is why the retry paths are asymmetric: a note in `error` must be re-processed (restoring `processed`) before it can be dispatched again — deliberate, since a failed dispatch may have been caused by the content itself.

**Step 4 — write the external reference only after the call returned.** The `update` sits *after* `createIssue`, inside the `try`, so a mid-call failure can never leave a note claiming an issue that does not exist:

```php
$result = $gitHubService->createIssue(/* … */);

$note->update([
    'status' => NoteStatus::SENT,
    'github_issue_number' => $result['number'],
    'github_issue_url' => $result['html_url'],
]);
```

The residual risk — issue created, response lost — is accepted and documented ([§ 6.3](A06-runtime-view.md#63-dispatch-to-github)): a duplicate issue is recoverable, a dangling reference is not.

> **Recipe — failure handling for a new pipeline action.** Follow the pattern of `process`/`send`: wrap the whole action in a single `try/catch (\Throwable)`; in the handler, `Log::error` with `note_id` and the exception message only (the redacting channel covers the rest — [§ 8.7](#87-logging)), write `status = NoteStatus::ERROR` plus `error_message`, and redirect back to the detail view. Leave every input (audio, transcript, generated content) untouched so the action stays re-runnable, and never persist a speculative external reference before the external call has succeeded.

## 8.7 Logging

Realisation of [N2.6](../spec/N2-querschnittskonzepte.md#n26-logging):

- **Channels.** Standard Laravel [Monolog channel stack](https://laravel.com/docs/13.x/logging#building-log-stacks) (`single`/`daily`/`stderr` per `config/logging.php`); the log file lives on the persistent surface `storage/logs/` ([S3.3](../spec/S3-inbetriebnahme.md#s33-persistent-state-surfaces)).
- **Redaction is structural, not disciplinary.** Every one of those channels carries the [tap](https://laravel.com/docs/13.x/logging#customizing-monolog-for-channels) `App\Logging\RedactSecrets`, which installs `SecretRedactionProcessor` into Monolog. The processor masks (a) the concrete values of `app.key`, `herold.api_key`, `herold.github.token`, `herold.openai.api_key` wherever they appear in message or context, (b) `Bearer`/`Authorization`/session-token patterns, and (c) any context key matching `token|api_key|secret|password|authorization|bearer|session_id|session_token|cookie`. Call sites cannot opt out — the enforcement point of [NFR-15b-03](../spec/N1-nichtfunktional.md#15b-integrity-requirements), verified by `LogRedactionTest`.
- **Reference, never embed.** Pipeline events log `note_id` and a failure reason only — never transcript, generated content, or metadata values (`Log::error('Voice note processing failed.', ['note_id' => …, 'error' => …])`).
- **Security events carry source IP.** Failed key/TOTP verifications, all three recovery rejection reasons, and successful recovery are logged with `$request->ip()` — the discriminating recovery reason exists *only* here, while the operator sees the uniform 404.

### 8.7.1 Walkthrough — redaction that call sites cannot bypass

**Step 1 — attach the tap to every channel.** The redaction is wired in configuration, not in a logger wrapper, so `Log::` used anywhere in the codebase is already covered (`config/logging.php`):

```php
'single' => [ /* … */ 'tap' => [RedactSecrets::class] ],
'daily'  => [ /* … */ 'tap' => [RedactSecrets::class] ],
'stderr' => [ /* … */ 'tap' => [RedactSecrets::class] ],
```

A [tap](https://laravel.com/docs/13.x/logging#customizing-monolog-for-channels) receives the built channel and may modify its Monolog instance:

```php
class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SecretRedactionProcessor);
    }
}
```

Adding a channel means copying that one `tap` line — the single thing to remember when `config/logging.php` changes.

**Step 2 — the processor rewrites the whole record.** A Monolog processor sees every record before it is formatted; message, context, and extra are all passed through the filter, because a secret leaks just as easily from an exception message as from a context array:

```php
public function __invoke(LogRecord $record): LogRecord
{
    return new LogRecord(
        datetime: $record->datetime,
        channel: $record->channel,
        level: $record->level,
        message: $this->redact($record->message),
        context: $this->redactArray($record->context),
        extra: $this->redactArray($record->extra),
    );
}
```

**Step 3 — value patterns are built from the live config.** The processor does not guess at secret *shapes*; it takes the actual configured values and masks them literally wherever they occur, plus a few generic carriers:

```php
foreach (['app.key', 'herold.api_key', 'herold.github.token', 'herold.openai.api_key'] as $key) {
    $value = config($key);
    if ($value && strlen($value) >= 8) {          // length guard: never mask a short/empty value
        $patterns[] = preg_quote($value, '/');
    }
}

$patterns[] = '(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*';
$patterns[] = '(Authorization:\s*)\S+';
$patterns[] = '(session[_\-]?(id|token)\s*[=:]\s*)\S+';
```

This is why the concept is *structural*: an adapter that echoes an upstream error containing its own Bearer token is redacted without the call site knowing.

**Step 4 — context keys are masked by name, recursively.** Independently of the value patterns, any key that *looks* sensitive is replaced wholesale — nested arrays included:

```php
private const SENSITIVE_CONTEXT_KEYS = [
    'authorization', 'bearer', 'token', 'api_key', 'apikey',
    'secret', 'password', 'session_id', 'session_token', 'cookie',
];

foreach ($data as $key => $value) {
    if (is_string($key) && $this->isSensitiveKey($key)) {
        $result[$key] = '[REDACTED]';                  // substring match, case-insensitive
    } elseif (is_string($value)) {
        $result[$key] = $this->redact($value);
    } elseif (is_array($value)) {
        $result[$key] = $this->redactArray($value);    // recurse
    } else {
        $result[$key] = $value;
    }
}
```

**Step 5 — what call sites are still responsible for.** Redaction covers *secrets*; it does not cover *note content*, which is not secret but is private. That discipline stays with the call site — log the reference, never the payload:

```php
Log::error('Voice note processing failed.', ['note_id' => $note->id, 'error' => $e->getMessage()]);
Log::warning('Failed TOTP verification attempt.', ['ip' => $request->ip(), 'user_id' => $user->id]);
```

`LogRedactionTest` asserts the mechanical half ([NFR-15b-03](../spec/N1-nichtfunktional.md#15b-integrity-requirements)); the content half — reference instead of payload — is a call-site rule, enforced by review rather than by the processor.

## 8.8 Secret handling

Realisation of [N2.8](../spec/N2-querschnittskonzepte.md#n28-secret-handling) / [D2.6](../spec/D2-datentypen.md#d26-opaquesecret) `OpaqueSecret`:

| Secret | At rest | In transit / use |
|--------|---------|------------------|
| Operator API key | Never stored — only `users.api_key_hash` (SHA-256). | Presented once per sign-in; constant-time hash comparison; regenerated (not revealed) by recovery. |
| TOTP secret | `users.totp_secret` under Laravel's [`encrypted` cast](https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting) ([APP_KEY-encrypted](https://laravel.com/docs/13.x/encryption) ciphertext in SQLite). | Decrypted only inside `verifyTotpCode`; surfaced exactly once at enrolment as the provisioning URI. |
| Recovery token | Operator-chosen file content, deleted on redemption or expiry. | `hash_equals` against trimmed input. |
| OpenAI key, GitHub PAT | `.env` → `config('herold.openai.api_key')` / `config('herold.github.token')`; `.env` is never deployed and never overwritten ([§ 7.2](A07-deployment-view.md#72-infrastructure-level-2--release-pipeline)). | Attached server-side as Bearer tokens by the adapters; constructors fail fast when unset. |

Guards that keep secrets (and near-secrets) off the browser channel ([NFR-15b-01](../spec/N1-nichtfunktional.md#15b-integrity-requirements)/[-02](../spec/N1-nichtfunktional.md#15b-integrity-requirements)): the `User` model hides `password`, `remember_token`, `api_key_hash`, `totp_secret` from [serialisation](https://laravel.com/docs/13.x/eloquent-serialization#hiding-attributes-from-json); `MessageTypeRegistry` strips `preprocessing_prompt` from every browser-bound type projection; `SettingsController` exposes only GitHub owner/repo and the boolean TOTP state. `ApiSecurityTest` asserts all of it. Logs are covered by § 8.7.

### 8.8.1 Walkthrough — the four guards on a secret

**Guard 1 — never store what you only need to compare.** The operator's API key exists as a hash and nothing else; the only operation ever performed on it is equality, so the plaintext has no reason to survive:

```php
$user->update(['api_key_hash' => hash('sha256', $newApiKey)]);   // AuthController::processRecovery
```

`Str::random(64)` output is high-entropy, so SHA-256 without a salt is adequate here — this is a *token* comparison, not a password. `password` on the same table is a framework remnant and is `hashed`-cast for exactly the opposite reason.

**Guard 2 — encrypt what you must read back.** A TOTP secret cannot be hashed, since the server has to recompute codes from it. It therefore lives under Laravel's [`encrypted` cast](https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting), which keeps `APP_KEY`-derived ciphertext in SQLite and decrypts only on attribute access (`app/Models/User.php`):

```php
protected function casts(): array
{
    return [
        'totp_secret' => 'encrypted',        // ciphertext at rest, plaintext only in memory
        'password' => 'hashed',
        'totp_confirmed_at' => 'datetime',
    ];
}
```

Consequence to be aware of: losing `APP_KEY` invalidates the enrolled secret, which is precisely what the recovery path ([§ 8.4.1](#841-walkthrough--the-two-factor-handshake)) is there to repair.

**Guard 3 — declare what must never be serialised.** Hiding is declared on the model, not at the render site, so no controller can accidentally leak a column by passing the whole model to Inertia:

```php
#[Hidden(['password', 'remember_token', 'api_key_hash', 'totp_secret'])]
class User extends Authenticatable
```

The same principle applies one level up, to near-secrets. The prompt is not a credential but must not reach the browser ([NFR-15b-02](../spec/N1-nichtfunktional.md#15b-integrity-requirements)), so the registry projects rather than passes through (`MessageTypeRegistry::all`):

```php
return array_map(
    fn (array $type) => collect($type)->except(['preprocessing_prompt', 'needs_current_date_context'])->all(),
    config('herold.types', []),
);
```

And a settings screen builds its payload explicitly, listing what it *does* expose instead of filtering what it must not (`SettingsController::index`):

```php
return Inertia::render('Settings/Index', [
    'github' => ['owner' => config('herold.github.owner'), 'repo' => config('herold.github.repo')],
    'totp' => ['confirmed' => $user->hasTotpEnabled()],   // boolean state, never the secret
]);
```

**Guard 4 — resolve host secrets at the adapter edge, and fail fast.** `.env` values are read once, in the constructor, and are never handled by callers ([§ 8.3.1](#831-recipes-per-boundary)); the adapter attaches them itself:

```php
$this->token = $config['token'] ?? throw new RuntimeException('GitHub token is not configured.');

// … and every outbound call:
Http::withToken($this->token)->post(/* … */);
```

The token therefore appears in exactly two places — the config array and the request header — and in neither of them can it reach a response, a view, or (thanks to § 8.7) a log line.
