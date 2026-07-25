# Application-Owned AI Adapter over Laravel HTTP -- Variant Comparison

## Context

Herold calls two OpenAI operations: audio transcription and structured chat generation. The specification mandates the current OpenAI neighbours while requiring each integration point to remain replaceable without changes to controllers, models, or the UI.

The decision is where provider-specific endpoint, authentication, request, and response knowledge lives. It is separate from the choice of OpenAI as the current provider.

---

## Option 1: Laravel AI SDK (`laravel/ai`)

**Pros:**
- First-party Laravel integration
- Potentially common abstractions across AI providers and capabilities
- Less direct endpoint and payload handling in application code

**Cons:**
- Adds a dependency whose capability and API lifecycle must cover both required seams
- A generic SDK abstraction may not match transcription and structured-output details equally well
- Provider portability would still require an application-facing boundary to keep SDK types out of the pipeline

---

## Option 2: Provider SDK

**Pros:**
- Provider-maintained request and response types
- Endpoint changes may be absorbed by the SDK

**Cons:**
- Couples application code to provider-specific types and release cadence
- Does not itself satisfy the provider-replaceability requirement
- Adds a dependency for two small integration surfaces

---

## Option 3: Laravel HTTP Client behind an Application-Owned Adapter

**Concept:** `AIService` exposes application-facing transcription and generation methods. Only that adapter knows OpenAI URLs, bearer authentication, payload shapes, response validation, and timeouts. It uses Laravel's existing `Http` facade for transport.

**Pros:**
- No additional runtime dependency
- Exact control over both API contracts and failure semantics
- Provider knowledge is confined to one adapter
- Tests can replace the adapter at the container boundary

**Cons:**
- Herold owns endpoint and payload maintenance
- Provider contract drift is detected by tests or runtime failures rather than absorbed by an SDK
- Switching provider requires replacing adapter internals rather than changing only an SDK configuration value

---

## Decision: Option 3 -- Application-Owned Adapter over Laravel HTTP

The current OpenAI integration uses Laravel's HTTP client inside `AIService`. Controllers and domain services depend only on the application-facing adapter methods and never construct provider requests themselves.

## Rationale

1. Herold has only two small, explicit AI integration seams.
2. The existing Laravel HTTP client already provides authentication, multipart uploads, JSON requests, timeouts, and test support.
3. Provider portability is achieved by the location and contract of `AIService`, not by exposing a generic SDK throughout the application.
4. Direct integration keeps response-shape validation and failure behaviour visible and under application control.
5. Introducing an SDK is worthwhile only if it demonstrably supports both seams while preserving this boundary.

## Consequences

- `AIService` is the only class that may know OpenAI endpoints and payload formats.
- API credentials remain server-side configuration and are attached in the adapter.
- Transport and response errors become exceptions that the pipeline's existing failure handling records for manual retry.
- Model identifiers are currently hard-coded in the adapter; this is a separate portability debt tracked as R-07/D-07(e).
- `laravel/ai` is not an installed or assumed dependency. Migration is tracked in [GitHub issue #41](https://github.com/carstenlucke/herold/issues/41); adopting it would amend or supersede this ADR.
- Automated tests continue to replace the application adapter rather than allowing SDK/provider types to leak into callers.
