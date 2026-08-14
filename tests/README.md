# Tests

The suite uses a self-contained runner (no Composer/PHPUnit dependency required):

```bash
php tests/run.php          # run everything
```

A PHPUnit-compatible layout is also provided via `composer.json` (`composer test`)
for those who want it.

## What is actually executed (TESTED)

These run against a **throwaway** `slc_ai_sales_test` database (the real
`slc_ai_sales` is never touched). Last run: **73/73 passed.**

| Suite | Covers |
|-------|--------|
| `AuthTest` | bcrypt hashing, `password_verify`, plaintext never stored, successful/wrong/unknown login, **rate-limit lockout**, logout, change-password |
| `ProtectedRouteTest` | no session ⇒ not authenticated, session ⇒ authenticated, **CSRF required for state changes**, inactive user blocked |
| `SecurityTest` | CSRF token stability/rejection, Validator (required/email/in/integer) pass+fail, `.env.example` carries no key |
| `CompanyTest` | create/read/update/soft-delete, AI-score clamping, search+filter, related contacts/leads/activities |
| `ContactTest` | CRUD linked to an existing company, boolean-flag normalisation |
| `LeadTest` | pipeline CRUD, joined list, status enum |
| `CampaignTest` | lifecycle + activate/pause, lead sequence with **no duplicate members** |
| `FollowupTest` | create/update/complete/hard-delete |
| `OpportunityTest` | CRUD, open-value rollup, probability clamping |
| `AiSettingsTest` | key **encrypted at rest**, browser view **masks key**, Crypt round-trip, masking, **obsolete models rejected**, default = `gemini-3.6-flash`, JSON extraction from fenced responses |
| `AiDiscoveryValidationTest` | discovery refuses without key, prompt contains anti-hallucination rules + `google_search` + JSON contract, research/email prompts correct |
| `DeduplicationTest` | match by name / domain / phone, null when no match |
| `DatabaseIsolationTest` | only `slc_ai_sales*` used, schema references no ERP tables, all 17 tables exist, no cross-DB queries, schema idempotent |
| `ProviderConfigTest` | provider key **encrypted at rest**, browser view **masks key** (raw never returned), ready = enabled+key, all 5 providers seeded |
| `ProviderFallbackTest` | AI router: no providers → error; **stops at first success** (no over-call); falls through on failure; all-fail aggregated; grounding never enabled |
| `ProviderLayerTest` | cache store/reuse + miss; usage/audit logging (success, cache-hit, error); provider tables exist |
| `FreeModeDiscoveryTest` | refuses when nothing configured; refuses with AI-only (demands Hunter/Apollo); never fabricates; never auto-saves |

## End-to-end HTTP checks (also TESTED, via PHP built-in server + curl)

- `GET /login` → 200 · `GET /dashboard` (no session) → 302 to login
- `GET /api/dashboard/stats` (no session) → **401**
- `POST /api/auth/login` wrong password → 401; correct → 200 + session
- `POST /api/companies` **without** CSRF → **419**; **with** CSRF → **201**
- `GET /api/dashboard/stats` (authed) → 200 with live KPIs
- `POST /api/ai/leads/discover` with no key → **503** "Gemini is not configured"
- `PUT /api/ai/settings` with `gemini-1.5-flash` → **422** (obsolete)
- `POST /api/ai/test-connection` with no key → `configured:false`
- `POST /api/auth/logout` → 200; afterwards → **401**
- `GET /api/ai/settings` never returns the raw key (masked only)

## NOT tested (honest disclosure)

- **No live Gemini API call was made** during development — no API key was
  available and the task explicitly forbade it. The Gemini provider, grounding
  request shape (`google_search`), response parsing, citation extraction and
  JSON extraction are unit-covered, but a real grounded call is **unverified**
  until a key is supplied by the user and "Test Connection" is clicked.
- Email sending is intentionally absent, so there is nothing to test there.
