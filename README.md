# SLC AI Sales Agent

**Shree Label Creation** — *Narrow-Web Flexographic Label Manufacturer*

An AI-powered B2B sales-intelligence and lead-discovery application. It uses
**Google Gemini (Interactions API) with Google Search Grounding** to discover
**real** companies on the live web, research them, score their relevance for
Shree Label Creation, and let the user selectively save verified prospects into
the app's **own** CRM.

> ⚠️ **Standalone & ERP-isolated.** This app uses **only** the `slc_ai_sales`
> database. It never connects to, reads, writes, or inspects the existing
> Shree Label Creation ERP database.

---

## 1. Features

- **Real authentication** — login/logout, `password_hash()`/`password_verify()`,
  PHP sessions, session regeneration, CSRF, brute-force rate limiting, session
  timeout. **No auto-login**, no hardcoded user id.
- **CRM (full CRUD)** — Companies, Contacts, Leads, Campaigns (draft sequence
  builder, no sending), Follow-ups, Opportunities, Email drafts, Research reports.
- **Dashboard** — every KPI computed live from MySQL (no hardcoded numbers).
- **AI Lead Finder** — Gemini + `google_search` grounding discovers real
  companies; review screen with select/deselect; save selected to CRM; built-in
  **deduplication**; strict **anti-hallucination** rules.
- **AI Research** — fresh grounded research per company, saved to
  `slc_research_reports` with citations.
- **AI Email Drafts** — personalised B2B drafts saved as **draft only**
  (no email transport anywhere in the app).
- **AI Settings** — configure the key (masked, server-side only), model, and a
  real **Test Connection** that calls Gemini only on click.
- **Integrations** — truthful statuses only (Gemini active when configured; all
  others Standby/Not Connected).

---

## 2. Tech stack

| Layer    | Tech |
|----------|------|
| Backend  | PHP 8.2+, PDO, MySQL 8+/MariaDB |
| Frontend | HTML5, CSS3, modular vanilla JS (no build step) |
| AI       | Gemini via **Interactions API** (`/v1beta/interactions`) + `google_search` tool |
| Server   | Apache (XAMPP) with `mod_rewrite` |

No Node.js, npm, React, Vite, Docker, or Python is required to run the app.

---

## 3. Project structure

```
slc-ai-sales/
├── config/                  (reserved for app config overrides)
├── database/
│   ├── schema.sql           ← single source of truth for the schema
│   └── Installer.php        ← idempotent installer (runs schema.sql)
├── src/
│   ├── bootstrap.php        ← autoloader + env load (no Composer needed)
│   ├── helpers.php          ← e(), je(), money(), reltime()
│   ├── Core/                ← Database, Auth, Session, CSRF, Validator,
│   │                          Router, Config, Env, Security, Crypt, HttpClient,
│   │                          RateLimiter, Response, Logger
│   ├── Models/              (domain models — DTOs/validation where used)
│   ├── Repositories/        ← BaseRepository + entity repositories (SQL)
│   ├── Controllers/         ← Auth, Company, Contact, Lead, Campaign,
│   │                          Followup, Opportunity, Dashboard, Email,
│   │                          Research, Integration, Activity, Ai, Page
│   └── Services/AI/         ← AIProviderInterface, GeminiProvider,
│                              AIServiceManager, LeadDiscoveryService,
│                              CompanyResearchService, EmailGenerationService,
│                              PromptBuilder, AiResult, AiRequestLogger
├── public/assets/
│   ├── css/app.css
│   └── js/                  ← app, api, auth, ui, modals, dashboard, companies,
│                              contacts, leads, campaigns, followups, opportunities,
│                              ai-settings, ai-lead-finder, ai-research,
│                              email-composer, research, integrations, profile
├── templates/               ← layout, sidebar, header + one template per page
├── routes/api.php           ← REST route table
├── tests/                   ← PHPUnit test suite
├── storage/                 ← logs + framework cache (web-protected)
├── .env.example
├── composer.json
├── setup.php                ← installer entry point
├── index.php                ← front controller (web pages + API)
├── login.php
└── README.md
```

No single file is a giant monolith. The largest are `app.css` (styles) and the
two AI-heavy modules; PHP controllers/templates stay small and focused.

---

## 4. Installation (XAMPP)

1. Copy the project folder to your XAMPP htdocs, e.g.
   `C:\xampp\htdocs\slc-ai-sales`.
2. Copy `.env.example` → `.env` and set your MySQL credentials
   (and optionally `APP_BASE_PATH=/slc-ai-sales`).
3. Make sure Apache's `mod_rewrite` is enabled and `AllowOverride All` is set
   for the htdocs directory (so the bundled `.htaccess` works for clean URLs).
4. Open **`http://localhost/slc-ai-sales/setup.php`** in a browser.
5. Confirm the DB details and click **Run Installation**. This:
   - creates the `slc_ai_sales` database (if missing),
   - creates all tables (`CREATE TABLE IF NOT EXISTS` — safe to re-run),
   - seeds truthful integration statuses + AI defaults,
   - creates the admin user (`admin@shreelabel.com` / `admin123`) — password
     stored **only** as a bcrypt hash.
6. Click **Go to Login** and sign in.

**The app runs in CRM-only mode with no Gemini key.** To enable AI:

1. Sign in → **AI Settings**.
2. Paste your Gemini API key (from <https://aistudio.google.com/apikey>).
3. Click **Test Connection**, then **Save Configuration**.

### Re-running setup is safe
`setup.php` never drops tables or resets data/passwords. The admin user is
created only if it does not already exist.

---

## 5. Multi-Provider, Free-First AI (no Google billing required)

The app is **free-first by default**. Gemini is now **optional**, and Gemini
Search Grounding is **not** required for Lead Discovery.

### Providers
| Provider | Role | Notes |
|----------|------|-------|
| **Hunter** | discovery + email enrichment | domain-search (free tier) is the primary company/domain discovery; email-finder used **only** when an email is missing |
| **Apollo** | people enrichment | People Search for decision makers; Organization Search is **not** auto-used in free mode (credit cost) |
| **FreeLLMAPI** | AI (primary) | OpenAI-compatible; candidate generation + qualification |
| **9Router** | AI (fallback) | OpenAI-compatible |
| **Gemini** | AI (optional fallback) | only used if FreeLLMAPI + 9Router both fail |

AI routing (first success wins, no retry storms):
`FreeLLMAPI → 9Router → Gemini(optional) → AI unavailable`.

### Lead Finder workflow
`input → AI candidate domains → Hunter discovery/enrichment (source of truth) →
Apollo people search → Hunter email-finder (only if missing) → normalize →
deduplicate against CRM → AI qualification → review → (user selects) → save`.

**Anti-hallucination:** company/contact/email/website data comes **only** from
providers. The AI only (a) suggests candidate domains and (b) assesses verified
facts. Verified prospects are badged ✓; AI-suggested-only ones are "Unverified".

### Cost / credit protection
- Every external call goes through `ProviderContext`: cache → one request →
  cache-store → audit-log. The same lookup is never repeated within TTL.
- `slc_provider_usage` logs provider, operation, cache-hit, status, latency,
  credits/remaining (where exposed), and errors.
- On a free-limit hit a clear provider error is shown — no silent paid fallback.
- Per-provider **Test Connection** makes at most **one** call and never retries.
- API keys are server-side only (encrypted at rest); the browser sees masks.

### Database tables added (idempotent, additive)
`slc_provider_config`, `slc_provider_usage`, `slc_provider_cache`.

### Gemini (optional)
If configured, Gemini is a last AI fallback (Interactions API, `gemini-3.6-flash`).
Grounding/search is **off** in the chain. The app works fully on `FreeLLMAPI → 9Router`.

---

## 6. Gemini / Google Search Grounding (optional provider)

- **API:** Gemini **Interactions API**
  `POST https://generativelanguage.googleapis.com/v1beta/interactions`
- **Model:** `gemini-3.6-flash` (configurable). Obsolete models
  (`gemini-1.5-*`, `gemini-2.0-flash`) are rejected.
- **Grounding tool:** `{"type": "google_search"}` — the **current** tool
  (not the obsolete `google_search_retrieval`).
- **Citations** are extracted from `url_citation` annotations and shown in the UI.
- **All AI calls** go: Browser → PHP API → `GeminiProvider` → Gemini.
  The key is **server-side only**; the browser only ever sees a masked value.

### Anti-hallucination
Prompts instruct the model to return a field only if it is publicly verifiable,
else `null`. Every prospect must include at least one verifiable source URL.
The app never invents companies/phones/emails/people.

---

## 6. Security

- PDO prepared statements everywhere (no string-built SQL with user input).
- Output escaping in every template (`e()`) and JS (`SLC.escape`).
- CSRF token verified on all POST/PUT/PATCH/DELETE.
- Session auth + regeneration; idle timeout; login rate limiting (file-backed).
- Secrets encrypted at rest (`Crypt`, AES-256-CBC, APP_KEY-derived).
- `.env`, `src/`, `storage/` blocked from the web via `.htaccess`.
- The Gemini API key is **never** returned by any API response.

---

## 7. REST API (selected)

```
POST   /api/auth/login            POST /api/auth/logout           GET /api/auth/me
GET    /api/dashboard/stats       GET /api/dashboard/pipeline
GET    /api/companies             POST /api/companies             GET/PUT/DELETE /api/companies/{id}
GET    /api/contacts              POST /api/contacts              GET/PUT/DELETE /api/contacts/{id}
GET    /api/leads                 POST /api/leads                 GET/PUT/DELETE /api/leads/{id}
GET    /api/campaigns ...         POST /api/campaigns/{id}/activate|pause|leads
GET    /api/followups ...         GET /api/opportunities ...
POST   /api/ai/test-connection    GET/PUT /api/ai/settings
POST   /api/ai/leads/discover     POST /api/ai/leads/save-discovered
POST   /api/ai/research           POST /api/ai/generate-email
GET    /api/ai/providers          PUT /api/ai/providers/{slug}
POST   /api/ai/providers/{slug}/test   GET /api/ai/providers/usage
```

---

## 8. Tests

Run:

```bash
composer install        # optional, only for phpunit
vendor/bin/phpunit      # or: phpunit
```

The suite covers authentication, protected routes, CSRF, companies/contacts/
leads/campaigns/followups/opportunities CRUD, AI settings + model validation,
deduplication, AI discovery request validation, and database isolation. See
`tests/README.md` for what was actually executed.

---

## 9. Known limitations

- No email sending (by design — draft only). No inbound mail/reply tracking.
- Integrations other than Gemini are placeholders (truthful "Standby/Not Connected").
- AI quality depends on the Gemini API key being configured and live web results.
- Default credentials should be changed immediately after install.
