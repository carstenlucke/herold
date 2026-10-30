# Home-Rolled TOTP instead of a Library -- Variant Comparison

**Status:** Accepted — in effect since 2026-04-09 (initial implementation), documented retrospectively on 2026-10-30.

## Context

The second authentication factor (NFR-15a-01) is a time-based one-time password per RFC 6238. It is embedded in Herold's own sign-in flow: the API key is verified first, TOTP second; enrolment happens inline on the first sign-in; loss of either factor is handled by the file-based recovery of UC-03, which replaces both factors. The secret is stored encrypted in `users.totp_secret`, confirmation in `users.totp_confirmed_at`.

The question is whether secret generation, provisioning, and code verification come from a package or from application code. The deciding criterion is the size of the supply chain: every third-party package, and every package it pulls in, is an entry point for a compromised release or a hijacked maintainer account and must be watched for advisories. Herold keeps its dependencies few for that reason.

---

## Option 1: Laravel Fortify

**Concept:** First-party authentication backend including two-factor authentication.

**Pros:**
- Maintained by the framework vendor
- Complete 2FA flow including enrolment and recovery codes

**Cons:**
- Brings its own routes, controllers, and recovery codes, built around username/password login
- Competes with Herold's API-key first factor and file-based recovery instead of supporting them
- Most of the package would be disabled or overridden, yet all of it enters the supply chain

---

## Option 2: TOTP Library (e.g. `pragmarx/google2fa`)

**Concept:** Use a library for secret generation and code verification; keep the flow in Herold.

**Pros:**
- Algorithm implementation reviewed and used by a wide user base
- Small integration surface
- Configurable per call: drift window (`verifyKey(…, $window)`), and constant-time comparison is built in

**Cons:**
- Adds the library and its transitive dependency (`paragonie/constant_time_encoding`) to the supply chain for roughly a hundred lines of algorithm
- Its releases must be monitored and taken up like any other dependency

---

## Option 3: Home-Rolled RFC 6238/4226

**Concept:** Secret generation (`random_bytes`, 160 bit), Base32 codec, HOTP calculation (`hash_hmac` with SHA-1, dynamic truncation to six digits), and TOTP verification (30 s steps, ±1 step window, `hash_equals`) in a dedicated class, `App\Services\TotpService`, built only on PHP built-ins.

**Pros:**
- No additional package; the supply chain does not grow
- The algorithm is small, in one class, and documented in § 8.4
- Unit-testable in isolation, with a fixed timestamp, against the RFC 6238 test vectors

**Cons:**
- Security-relevant code is owned and verified by the project alone
- Edge cases a library would already handle must be known and tested by the project (e.g. the counter is packed as 32 bit, the Base32 decoder skips foreign characters)

---

## Decision: Option 3 -- Home-Rolled RFC 6238/4226

## Rationale

1. RFC 6238/4226 are short, standardised, and stable; PHP's `random_bytes`, `hash_hmac`, and `hash_equals` supply every primitive. The implementation risk is small and fully covered by the published test vectors.
2. A library would add to the supply chain without removing any flow logic from Herold. Avoiding that dependency outweighs the library's wider review for an algorithm this small.
3. Fortify would replace rather than support Herold's API-key login and file-based recovery, and would bring far more code than it uses.

## Consequences

- The TOTP code lives in `TotpService` and is injected into `AuthController`; § 8.4 / § 8.4.1 describe it.
- A unit test checks the implementation against the RFC 6238 SHA-1 test vectors and the drift window; acceptance tests cover both the rejection paths and a successful sign-in with a freshly generated code.
- The provisioning QR code is rendered client-side by the `qrcode` package; only the `otpauth://` URI crosses the Inertia boundary.
- Replacing the implementation by a library later is local to `TotpService` and does not change the stored secret format (Base32, SHA-1, 6 digits, 30 s).
