# Ledgerlight — Design

Date: 2026-11-15 · Status: draft for approval · Author: Pedro Huguenin

UI reference: [`docs/design/ledgerlight-ui.pdf`](../../design/ledgerlight-ui.pdf) (design system + all screens, light and dark). Page numbers below refer to that PDF.

## 1. Intent

Ledgerlight is a portfolio project built to stand out in an application for a **Laravel & Agentic AI** engineering role (FullStack Labs) and, more broadly, for any role asking for Laravel + Vue/Nuxt + production AI.

It is a multi-tenant SaaS that automates accounts payable: companies upload vendor invoices (PDF), an LLM extracts the data with a confidence score per field, humans review only what is uncertain, and approved invoices are exported to an accounting system that is deliberately unreliable.

The pitch in one line: **"AI you can verify."** Most teams can demo AI; few can put it in production and prove what it produced. Every number the model produced is traceable to its source, its confidence, its cost, and whoever changed it.

### Who it is for

- **Recruiter** (2 minutes): opens the live URL, clicks a demo account, sees a polished product, flips chaos mode, watches it recover.
- **Client engineer** (20 minutes): opens the repo, reads the README, sees tenant isolation proven by tests, a resilient integration client, an honest AI pipeline with evals, and a visible agentic workflow (`.claude/`, specs before code, PRs with review notes).

### Success looks like

- A public URL that is always up, loads fast, and costs **$0/month** to run.
- Every requirement in the job post maps to something concrete the reviewer can click or read (see §14).
- The candidate can explain every design decision in an interview, because the repo documents them.

## 2. Goals, non-goals, anti-goals

**Goals**
- Polished, coherent product across 9 screens (PDF pp. 3–16), light + dark, keyboard-first review.
- Real LLM extraction via Groq, within Groq's **free plan**.
- Multi-tenancy with isolation enforced at the database level and proven by tests.
- A simulated third-party accounting API ("Tallyline") with chaos mode, and a client that survives it: retries with backoff + jitter, idempotency keys, circuit breaker, dead-letter queue.
- AI audit trail ("extraction receipt") and an evals dashboard backed by a labeled synthetic dataset.
- Agentic workflow as a first-class artifact of the repo.
- Free, always-on deploy, CI/CD from day one.

**Non-goals**
- Real accounting integrations (QuickBooks, Xero), payments, vendor master data management.
- Self-serve sign-up, SSO, password reset, outbound email.
- Chat/assistant UI, marketing landing page, billing/plans, notification center.
- Multi-currency conversion (currency is extracted and displayed, never converted).
- Horizontal scaling. One VM is the deployment target.

**Anti-goals** (would make it a failure even if it works)
- Overclaiming: any UI copy that promises something the system doesn't do (e.g. "row-level security" on MySQL, "250 hand-labeled invoices" when they're synthetic).
- A demo that breaks when two recruiters use it at once, or burns the free AI quota so the next visitor sees errors.
- Cold starts or a "this app is sleeping" page.
- Feature sprawl: every screen element must earn its place.
- Model self-reported confidence presented as if it were calibrated truth.

## 3. Constraints

- **Stack**: Laravel (latest stable, PHP 8.4+) API, Nuxt (latest stable, Vue 3, TypeScript) frontend, MySQL 8.4 LTS, Redis, Docker Compose. Tailwind CSS with the tokens from PDF p. 1. Icons: Lucide via `nuxt-icon`.
- **AI provider**: Groq API, free plan only (no card attached). See §7.
- **Hosting**: Oracle Cloud Always Free (Ampere A1 ARM VM). Total running cost $0. See §11.
- **Language**: all code, docs, commits and UI in English. US formats (USD, MM/DD/YYYY).
- **Schedule**: no deadline. Polish over speed, but no feature without a clear purpose. Stages run in the order of §13.
- **Git**: public repo on GitHub account `hugueninfer`. One branch + PR per stage (§13; superseded, see the dated note there: commits go directly to `main`), many small commits inside, Conventional Commits, English, **no AI co-author trailers**.

## 4. Approaches considered

### 4.1 Application architecture
| Option | Trade-off | Decision |
|---|---|---|
| **Laravel API + Nuxt, monorepo, single DB with `tenant_id`** | Matches the job stack exactly; API design visible; isolation demonstrable by tests | **Chosen** |
| Laravel + Inertia + Vue monolith | Simpler, but drops Nuxt (explicitly required) and hides API design | Rejected |
| Database-per-tenant (stancl/tenancy) | Stronger isolation story, but complicates migrations, queues, demo cloning and tests with nothing a reviewer would see | Rejected |

### 4.2 Hosting
| Option | Trade-off | Decision |
|---|---|---|
| **Oracle Always Free ARM VM running Docker Compose** | Always on, same compose as dev, runs MySQL/Redis/workers/scheduler/mock API; needs a card at sign-up (no charge), occasional regional capacity shortage | **Chosen** |
| Free PaaS mix (Vercel + Render + TiDB + Upstash) | API sleeps (~50 s cold start), no free background worker, which breaks the async pipeline | Rejected |

### 4.3 Extraction pipeline on Groq
| Option | Trade-off | Decision |
|---|---|---|
| **Text layer → text model; scans → page images → vision model** | Cheapest in tokens, fast; text layer also enables source highlighting and grounding | **Chosen** |
| Everything as images to a vision model | Simpler single path, but ~2k tokens per page eats the free quota and loses grounding | Rejected |
| Local OCR (Tesseract) → text model | No vision dependency, but heavier image, worse quality on tables, more moving parts | Kept as fallback idea only, not built |

## 5. Product scope

### 5.1 Tenants, roles, demo identities
- A **tenant** is one company. Users belong to exactly one tenant.
- Roles: **Uploader** (upload, track own and team invoices), **Reviewer** (Uploader + review, correct, approve, reject, AI audit), **Admin** (Reviewer + Exports, Quality, Settings, chaos mode).
- Navigation adapts to role; forbidden routes render the 403 state (PDF p. 11), cross-tenant resources render 404 (same response whether or not the ID exists).

### 5.2 Screens (all from the PDF; changes noted)
1. **Sign in + demo picker** (p. 4). Changes: the left column keeps the logo, headline and pitch but **drops the email/password form, SSO and "Forgot password?"** — there are no real accounts, and demo users live in per-visitor sandboxes where emails aren't globally unique, so a password form would promise something the system doesn't do. *(Builder-check call.)* Replace the RLS copy with the truthful mechanism (§9). Demo panel gets one extra link: "Start with an empty company" → onboarding state (p. 12).
2. **App shell / sidebar** (p. 3). "Demo workspace" card copy changes to describe private sandboxes (§10).
3. **Invoices inbox** (p. 5, mobile p. 13, dark p. 14). Upload dropzone with per-file progress and pipeline steps; status tabs with counts; search; date filter; pagination.
4. **Review queue** = Invoices filtered to *Needs review*, sorted lowest confidence first.
5. **Review invoice** (p. 6, dark p. 15). PDF viewer with source highlight, extracted fields with confidence, inline corrections ("Corrected by you · AI read X · Undo"), line items, validation checks, approve/reject (reason required), activity timeline, keyboard shortcuts.
6. **AI audit / extraction receipt** (p. 7). Model shown is the Groq model actually used. "Replay with vN" runs in a sandbox and never mutates the invoice.
7. **Exports & integration health** (p. 8, outage p. 16). Health, circuit breaker, attempts log, dead-letter queue, chaos mode.
8. **Quality / evals** (p. 9). Copy changes: "Scored on the same N labeled invoices" (synthetic, §8).
9. **Settings** (p. 10): Team (invite creates a *pending* member; no email and no invite link — instead a dashed "Demo control" action, styled like chaos mode, simulates the invitee accepting, so role changes and removal can be exercised) *(builder-check call)*, Approval rules, Integration (read-only Tallyline sandbox connection status + reconnect).
10. **States** (p. 11): skeletons, extracting, error with request id, 403, 404, export failure on the invoice, toasts, reject dialog, empty search, shortcut sheet.

**Cut from the design**: "Require 2 reviewers above $X" guardrail (adds a dual-approval state machine for little demo value). Everything else on p. 10 stays.

### 5.3 Invoice lifecycle
```
Processing ──► Needs review ──► Approved ──► Exporting ──► Exported
     │               │                           │
     │               └──► Rejected (reason)       └──► Export failed (dead-letter) ──retry──► Exporting
     └──► Extraction failed (unreadable / password-protected / quota) → shown on the upload row
Processing ──► Approved  (auto-approve when rules allow, §6.3)
```
Transitions are explicit (a small state machine on the model, every transition writes an activity event with actor: user, AI, or System).

## 6. Domain

### 6.1 Core tables (all tenant-owned tables carry `tenant_id`)
- `tenants`, `users` (role enum), `invitations`
- `vendors` (normalized name, per tenant; created on first sighting; drives "first invoice from new vendor")
- `invoices` (status, file path, sha256, page count, has_text_layer, vendor_id, header fields as final values, overall confidence, timestamps, approved_by, rejected_reason)
- `invoice_line_items`
- `extractions` (immutable receipt: model, prompt version, schema version, temperature, timings per step, tokens in/out, cost, retries, raw output JSON, output hash, cache hit flag)
- `extraction_fields` (per field: AI value, confidence components, final value, changed_by, changed_at, source page, source text match)
- `validation_results` (check key, status pass/warn/fail, blocking flag, detail)
- `activity_events` (invoice_id, actor type/id, type, payload)
- `approval_rules` (one row per tenant: threshold, enabled, guardrails)
- `export_attempts` (invoice_id, attempt #, idempotency key, request id, HTTP status, latency, error code, next retry at, outcome)
- `integration_states` (per tenant: circuit state, opened_at, failure counters, chaos mode)
- `prompt_versions` (name@version, status candidate/production/retired, notes, eval metrics)
- `eval_runs`, `eval_results`
- `ai_usage_ledger` (daily token accounting for the free-plan budget, §7.5)

Money is stored as integer cents. Dates as `DATE`. All amounts displayed with tabular numerals, right-aligned.

### 6.2 Validation checks
| Check | Severity |
|---|---|
| Line items sum = subtotal | **Blocking** |
| Subtotal + tax = total | **Blocking** |
| Due date ≥ invoice date | Warning |
| Tax rate plausibility (shows %) | Info/pass line |
| Possible duplicate: same vendor + same invoice number, or same vendor + same total within 30 days | Warning, with "Compare" |
| Required fields present (vendor, invoice #, invoice date, total) | **Blocking** |

### 6.3 Auto-approve
An invoice skips review only if **all** hold: rules enabled; overall confidence ≥ threshold; no blocking check failed; total ≤ the "never above" amount (if enabled); vendor is not new (if enabled). Settings preview (p. 10) is computed from the last 30 days of real invoices in that tenant. Rule changes write an activity event and never affect already-approved invoices.

## 7. AI extraction (Groq, free plan)

### 7.1 Pipeline (queued job per invoice)
1. **Read PDF**: compute sha256; detect text layer (e.g. `pdftotext` / a PHP PDF parser). Reject password-protected/unreadable files with a clear reason.
2. **Choose path** (per page, *builder-check call*):
   - All pages have a text layer → send layout-preserving text (trimmed) to the **text model**.
   - Any page without a text layer (scanned or mixed document) → send the **vision model** the text of the digital pages plus images of the scanned pages (max 3 images, Groq vision limit). Documents with more than 3 scanned pages fail with a clear message.
   - Grounding (step 4) applies only to values found on digital pages; values from scanned pages are marked "from image" and use the scan weighting in §7.3.
3. **Call Groq** with Structured Outputs in **strict JSON-schema mode** (schema `invoice.vN`: every field `{value, confidence, note}`; nullable via `null` unions as strict mode requires).
4. **Ground** each value against the text layer (normalized match: dates, amounts, case/whitespace) and record the page/offset where found.
5. **Validate** (§6.2), compute confidence (§7.3), decide auto-approve (§6.3), write the immutable receipt, emit activity events.

Each step's duration is recorded (PDF p. 7 "Read PDF / Model call / Validation").

### 7.2 Models (config, not code)
As of 2026-11-15, Groq strict structured outputs are supported on `openai/gpt-oss-120b`, `openai/gpt-oss-20b`, `qwen/qwen3.8-27b` (vision, ≤3 images) and `meta-models/muse-spark-open` (limited preview). Plan:
- Text path: `openai/gpt-oss-120b`, fallback `openai/gpt-oss-20b`.
- Vision path: `qwen/qwen3.8-27b`.
Model IDs, prices (for the cost column) and limits live in `config/ai.php`; the receipt stores whatever was used. Groq's catalog changes often — **re-check models before stage 4**.

The Groq client is a thin, owned wrapper over Laravel's HTTP client (Groq is OpenAI-compatible): it reads `x-ratelimit-*` headers, honors `retry-after` on 429, and is trivially faked in tests with `Http::fake`.

### 7.3 Confidence (computed, not just self-reported)
LLM self-reported confidence is poorly calibrated, so per-field confidence combines:
- **m**: model's self-reported confidence (0–1)
- **g**: grounding — exact normalized match in text layer = 1, fuzzy = 0.5, not found = 0 (scans: g is omitted and weights renormalize)
- **v**: validation — 0 if the field participates in a failed check, else 1

`confidence = weighted blend of m, g, v`, starting weights documented in code and **tuned against the eval set** so that "High ≥ 90%" really means ≥ 90% empirical accuracy on the synthetic set. Buckets match the design: High ≥ 90, Medium 70–89, Low < 70.
**Overall invoice confidence** = the minimum across critical fields (vendor, invoice #, invoice date, due date, total) — conservative on purpose, because it gates auto-approve.

"Why medium?" explanations (press **E**) are generated from these components plus the model's `note` — no extra LLM call.

### 7.4 Prompt versioning
Prompts live as versioned files (`resources/prompts/extract-invoice/v14.md`) registered in `prompt_versions`. Production version is a DB flag per environment. Every receipt records the exact version and schema.

### 7.5 Staying inside the free plan
Groq free-plan limits are per model and per organization (requests/min, requests/day, tokens/min, tokens/day). Exact numbers are read from the account's limits page at setup and stored in config. The design keeps usage far below them:

1. **Demo data is pre-extracted.** Seeded invoices come with real receipts captured once (by running the pipeline locally), so browsing the demo costs zero tokens.
2. **Result cache by content.** Key = `sha256(file) + prompt version + model`. "Try 5 sample invoices" and re-uploads of known files hit the cache: real output, zero tokens. Receipts show "cache hit".
3. **Global daily budget** tracked in `ai_usage_ledger` + Redis, set to ~70% of the free tokens/day across models, leaving headroom.
4. **Rate shaping**: extraction jobs go through a Redis rate limiter sized to the per-minute limits; jobs wait instead of failing.
5. **Fallback chain across models**: limits are per model, so when the primary is exhausted the text path falls back to the second model (receipt records it).
6. **Per-sandbox cap** on live (non-cached) extractions per day (e.g. 5), shown in the UI.
7. **Graceful exhaustion**: when the budget is spent, uploads still succeed and show an honest state: "Today's live AI quota is used up. Try the sample invoices, or come back tomorrow." No stack traces, no silent failure.
8. **Evals and replays don't run against the public quota**: full eval runs are an artisan command run locally/CI with the developer's own free key; results are stored and displayed. In the public demo, "Run eval" and "Replay" are served from cache or disabled with a tooltip explaining why.

No paid upgrade path is configured; hitting a limit can never generate a bill.

## 8. Synthetic invoices & evals

A generator (artisan command) produces realistic US vendor invoices as PDFs **with known ground truth**:
- Several templates (layouts, fonts, single/multi-page, terms like "Net 30" without explicit due date, PO numbers with ambiguous O/0, missing lines across page breaks, duplicates).
- Two renditions: digital (text layer) and "scanned" (rasterized, skew, noise, blur, no text layer).
- Ground truth stored as JSON next to each PDF.

- A third rendition, **mixed**: some pages digital, some scanned (needed for the INV-2207 story, PDF p. 6–7).

Uses: (a) seed data for demo tenants, (b) the eval set ("N labeled invoices", N chosen to fit the free quota, e.g. 60–100), (c) tuning confidence weights, (d) Playwright fixtures.

**Honest demo history** *(builder-check call)*:
- Every seeded invoice's AI output is a **real Groq extraction** captured during development (free quota allows roughly 80–90 extractions/day across the two text models, so a few days of runs cover it). Demo inbox volume is sized to what was really extracted (target ~120 invoices for Acme, ~20 for Globex), not to the mockup's numbers.
- Prompt versions are **real**: v1, v2, v3… actually written and evaluated during stage 10, each with its real eval metrics and real dates. The mockup's v12–v15 become whatever versions exist.
- Production-style trends (8-week accuracy, correction rate, auto-approve share, the threshold histogram) are computed from the seeded invoices, whose **review actions and timestamps are simulated** across past weeks. The Quality page states this in one line: "Demo history: real AI extractions, simulated review dates."
- No metric is typed in by hand.

**Quality dashboard** metrics: field accuracy (vs. human-approved final values in production; vs. ground truth in evals), human correction rate, auto-approved share, avg cost/invoice (computed from Groq list prices even though the plan is free, labeled as such), latency p50/p95, per-prompt-version comparison. Seeded history for the demo tenant is clearly derived from generated activity, not hand-faked numbers.

## 9. Multi-tenancy & authorization

- `tenant_id` on every tenant-owned table; a global Eloquent scope applied via a `BelongsToTenant` trait; tenant resolved from the authenticated user (never from the request).
- **Database-level guarantee without RLS** (MySQL has none): composite unique keys `(tenant_id, id)` and **composite foreign keys** `(tenant_id, x_id) → x(tenant_id, id)` make cross-tenant references impossible in the database itself.
- Policies per role for every action; route model binding resolves inside the tenant scope so foreign IDs 404.
- **Proven by tests**: a dedicated test suite iterates every API route as a user of tenant B against tenant A resources and asserts 404/403; a DB test asserts the composite FKs reject cross-tenant inserts.
- Login page copy: "Tenant isolation is enforced in the database with tenant-scoped foreign keys and tested on every parameterised route. Try opening an Acme invoice URL while signed in as Globex: you get a 404."
- Auth: Sanctum SPA cookie auth. Nuxt and API served from the **same origin** behind Caddy (`/` → Nuxt, `/api` → Laravel) to avoid CORS and cross-site cookie issues.

## 10. Demo mode

- **Private sandboxes**: clicking a demo account clones a fresh pair of tenants (Acme Logistics + Globex Foods, with their users and data) for that visitor and signs them in. Sandboxes expire after 24 h and are purged by the scheduler. This prevents visitors from colliding (chaos mode, approvals) and removes the need for an hourly global reset. Sidebar copy: "Private demo workspace · expires in 24 h · Switch demo account".
- Switching demo account stays within the same sandbox pair, so the Globex isolation test works.
- "Start with an empty company" creates an empty sandbox tenant (Bluebird Dental Group) to show onboarding.
- Cloning must be fast (< 1 s): bulk inserts from a template snapshot, files shared read-only by content hash.
- Abuse limits: sandboxes per IP per day, upload size/count (20 MB, 25 files as designed), live extraction cap (§7.5).

## 11. Exports, Tallyline and resilience

**Tallyline** is a separate minimal service (its own container and storage) pretending to be an accounting API: `POST /v1/bills`, `GET /v1/bills/{id}`, `GET /v1/vendors`, `POST /v1/vendors`. It enforces `Idempotency-Key`, returns realistic errors (422 `vendor_not_found`, 409 `duplicate_bill`, 429 with `Retry-After`, 503, 504/timeouts) and has a per-tenant chaos setting: **Off**, **Flaky** (30% failures across 503/429/timeout, +800 ms latency), **Outage** (every call fails).

**Recovering from `422 vendor_not_found`** *(builder-check call)*: Tallyline also exposes `POST /v1/vendors`. The dead-letter item and the invoice's export-failure banner offer **"Create vendor in Tallyline & retry"**, which creates the vendor (idempotently) and re-queues the export. Ledgerlight never auto-creates vendors, so the failure and its recovery are both visible. This is the only vendor action in the product; vendor management stays a non-goal.

**Ledgerlight's client**:
- Exports are queued jobs; idempotency key `inv_{id}:export:{n}`.
- Retries: exponential backoff `2s × 2^n + jitter`, max 5 attempts; 429 honors `Retry-After`; 4xx business errors (422) are non-retryable and go straight to dead-letter with guidance.
- Circuit breaker per tenant (state in Redis): opens after 5 consecutive failures, short-circuits calls while open (invoices stay queued), probes every 30 s (half-open), closes on success.
- Dead-letter queue with manual Retry and "Retry all failed".
- Health: Healthy / Degraded / Circuit open derived from the last 5 minutes of attempts; the page polls every 2 s.

## 12. Frontend

- Nuxt (SSR on for the sign-in page, client-rendered app shell is fine), TypeScript strict, Tailwind with the CSS-variable tokens of p. 1 (`data-theme="dark"`).
- Components map 1:1 to the design (`components/ui/`): `ConfidenceMeter`, `StatusBadge`, `UiButton`, `FieldInput`, `StatusTabs`, `Segmented`, `ValidationCheck`, `InvoiceRow`, toasts via `useToast()`.
- PDF viewer: pdf.js; source highlight by searching the field's grounded text in the page's text layer.
- Live updates via short polling (2 s while something is processing/exporting, paused otherwise). No websockets — not worth the moving parts here.
- Keyboard map from p. 6/11: J/K, Shift+N / N, E, ⌘/Ctrl+Enter, ], ?, / for search.
- Accessibility: WCAG AA contrast (tokens already checked), confidence readable without color (bars + word), screen-reader labels as specified on p. 2.
- Mobile: inbox cards (p. 13); review on phone limited to approving high-confidence invoices.

## 13. Delivery: stages, branches, commits

> **Note (2026-11-15):** the owner chose to commit directly to `main` instead of using one branch and PR per stage. Each stage's Outcome section in `docs/specs/` and its `stage-NN` tag replace the PR description and review notes.

Each stage = one branch + one PR with a short description and review notes, many small Conventional Commits inside (English, no co-author trailers). Each stage starts with a mini-spec in `docs/specs/NN-<stage>.md` written before code.

0. **Repo & agent setup** — README skeleton, `.claude/` (CLAUDE.md, rules, hooks), AGENTS.md, Conventional Commit lint, editorconfig.
1. **Infra** — Docker Compose (dev + prod overlay), Caddy, MySQL, Redis, multi-arch images, CI skeleton (lint + tests).
2. **Auth & tenancy** — tenants, users, roles, Sanctum, scopes, composite FKs, isolation test suite.
3. **Design system & app shell** — tokens, base components, layout, dark mode, sign-in page.
4. **Upload & storage** — upload API, file validation, inbox list, statuses, polling.
5. **Extraction pipeline** — Groq client, prompts, schema, grounding, confidence, receipts, budget/cache/rate limiting.
6. **Review** — review screen, corrections, validation checks, approve/reject, activity, shortcuts.
7. **Approval rules** — auto-approve and guardrails, settings preview.
8. **Tallyline & exports** — mock service, resilient client, circuit breaker, DLQ, chaos mode, exports page.
9. **AI audit** — receipt page, diff, raw output, replay (cached).
10. **Synthetic invoices & evals** — generator, eval command, quality dashboard.
11. **Team settings** — members, roles, pending invites.
12. **Demo mode** — sandbox cloning, demo picker, expiry, abuse limits, empty-company onboarding.
13. **Deploy** — Oracle VM provisioning notes, GitHub Actions build (arm64) → GHCR → deploy over SSH, HTTPS, backups of nothing important (demo is disposable), health checks.
14. **Polish** — README story, screenshots/GIF, `docs/ai-workflow.md`, `docs/architecture.md`, final accessibility and performance pass.

Order can be adjusted, but deploy (stage 13) is exercised early: a "hello" deploy is done at the end of stage 1 so that every later stage ships live.

### Testing & CI
- Backend: Pest (feature tests for every endpoint, tenancy suite, pipeline with faked Groq, Tallyline client against a faked server, circuit breaker unit tests), Larastan (high level), Pint.
- Frontend: Vitest for components (ConfidenceMeter, FieldInput states), ESLint, `nuxi typecheck`.
- E2E: Playwright smoke on the compose stack — demo login, upload sample, review & approve, chaos outage → recovery.
- GitHub Actions: lint + tests on PR; on `main`, build arm64 images, push to GHCR, deploy, run a post-deploy health check.

## 14. Job requirement → evidence map (for the README)

> **Note (2026-11-15):** the owner chose to commit directly to `main` instead of using one branch and PR per stage. Each stage's Outcome section in `docs/specs/` and its `stage-NN` tag replace the PR description and review notes.

| Requirement | Where a reviewer sees it |
|---|---|
| Production APIs in PHP/Laravel | `api/`, endpoint tests, error contract with request ids |
| Multi-tenant UI in Vue/Nuxt | `web/`, role-aware navigation, demo picker |
| Shipped with AI coding agents; spec → steer → review | `docs/specs/`, PR descriptions with "where I corrected the agent", `docs/ai-workflow.md` |
| Project rules, context hooks, guidelines for agentic platforms | `.claude/` (CLAUDE.md, rules, hooks), `AGENTS.md` |
| MySQL data model + Redis caching | migrations with composite FKs, result cache, rate limiter, circuit breaker state |
| Docker & Docker Compose | dev + prod compose, multi-arch images |
| Unreliable third-party APIs | Tallyline + chaos mode + resilient client; Groq limits handling |
| Multi-tenant permission model | policies, isolation test suite, 403/404 behaviors |
| CI/CD | GitHub Actions → GHCR → live VM |
| AI in production you can prove | receipts, grounding, computed confidence, evals |

## 15. Prerequisites the owner provides

Before the end of stage 1 (first live deploy):
- **Oracle Cloud account** (card required at sign-up, no charges on Always Free). The home region is permanent: pick one with good Ampere A1 availability. If A1 capacity is unavailable, keep retrying provisioning; development continues locally and the "hello deploy" moves to whenever the VM exists.
- **Groq account + free API key** (no card), and a note of the free-plan limits shown in the console.
- **DuckDNS** (or similar) hostname pointed at the VM.

## 16. Left to the builder

- Exact Laravel/Nuxt/PHP versions (latest stable at stage 1, pinned).
- PDF text/raster tooling (poppler-utils vs. pure PHP), as long as it runs on arm64.
- Starting weights of the confidence blend (must be documented and tuned in stage 10).
- Exact free-plan limits and budget numbers (read from the Groq console at setup).
- Tallyline implementation details (framework, storage) as long as it is a separate container with its own state.
- Domain: a free hostname (e.g. DuckDNS) by default; a custom domain can be swapped in later.
