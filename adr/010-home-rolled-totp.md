# Home-Rolled TOTP instead of a Library -- Variant Comparison

## Context

The second authentication factor (NFR-15a-01) is a time-based one-time password per RFC 6238. It is embedded in Herold's own sign-in flow: the API key is verified first, TOTP second; enrolment happens inline on the first sign-in; loss of either factor is handled by the file-based recovery of UC-03, which replaces both factors. The secret is stored encrypted in `users.totp_secret`, confirmation in `users.totp_confirmed_at`.

The question is whether secret generation, provisioning, and code verification come from a package or from application code.

---

## Option 1: Laravel Fortify

**Concept:** First-party authentication backend including two-factor authentication.

**Pros:**
- Maintained by the framework vendor
- Complete 2FA flow including enrolment and recovery codes

**Cons:**
- Brings its own routes, controllers, and recovery codes, built around username/password login
- Competes with Herold's API-key first factor and file-based recovery instead of supporting them
- Most of the package would be disabled or overridden

---

## Option 2: TOTP Library (e.g. `pragmarx/google2fa`)

**Concept:** Use a library for secret generation and code verification; keep the flow in Herold.

**Pros:**
- Algorithm implementation reviewed by a wider user base
- Small integration surface

**Cons:**
- Additional dependency for a handful of functions
- Drift window, comparison semantics, and Base32 handling follow the library's defaults and release cycle

---

## Option 3: Home-Rolled RFC 6238/4226

**Concept:** Secret generation (`random_bytes`, 160 bit), Base32 codec, HOTP calculation (`hash_hmac` with SHA-1, dynamic truncation to six digits), and TOTP verification (30 s steps, ±1 step window, `hash_equals`) implemented as private methods in `AuthController`.

**Pros:**
- No dependency
- The algorithm is fully visible in a few dozen lines and documented in § 8.4
- Exact control over drift window and constant-time comparison

**Cons:**
- Security-relevant code owned and verified by the project alone
- Correctness depends on the project's own tests

---

## Decision: Option 3 -- Home-Rolled RFC 6238/4226

## Rationale

1. RFC 6238/4226 are short, standardised, and stable; PHP's `random_bytes`, `hash_hmac`, and `hash_equals` supply every primitive.
2. Fortify would replace rather than support Herold's API-key login and file-based recovery.
3. A library would add a dependency without removing any flow logic from Herold.

## Consequences

- The TOTP code lives in `AuthController` and is described in § 8.4 / § 8.4.1.
- The acceptance tests currently cover the rejection paths (wrong code, malformed code, TOTP before API key, unconfirmed enrolment, rate limiting). A positive check against the RFC 6238 test vectors is not yet part of the suite.
- The provisioning QR code is rendered client-side by the `qrcode` package; only the `otpauth://` URI crosses the Inertia boundary.
- Replacing the implementation by a library later is local to `AuthController` and does not change the stored secret format (Base32, SHA-1, 6 digits, 30 s).
