# Plan 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A monorepo with agent rules and hooks, a Dockerized Laravel API + Nuxt app behind Caddy, CI, a live "hello" deploy on Oracle Cloud, and multi-tenancy with isolation enforced by composite foreign keys and proven by tests.

**Architecture:** `api/` (Laravel 13 on FrankenPHP) and `web/` (Nuxt 4) run in Docker Compose behind a Caddy edge that serves `/` → Nuxt and `/api/*`, `/sanctum/*` → Laravel on one origin. Tenancy is a `tenant_id` column + global scope resolved from the authenticated user, backed by composite `(tenant_id, id)` keys and composite foreign keys in MySQL.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, Larastan, Pint, Sanctum · Nuxt 4.6, Vue 3, TypeScript, Tailwind CSS 4 (`@tailwindcss/vite`), Vitest, ESLint (`@nuxt/eslint`), pnpm · MySQL 8.4, Redis 8 · Docker Compose, Caddy 2 · GitHub Actions, GHCR · Oracle Cloud Always Free (Ampere A1, arm64).

**Spec:** [docs/superpowers/specs/2026-11-15-ledgerlight-design.md](../specs/2026-11-15-ledgerlight-design.md) (§3, §9, §11 hosting, §13 stages 0–2, §15 prerequisites)

## Global Constraints

- Everything in English: code, comments, docs, commit messages, UI copy.
- Conventional Commits (`type(scope): subject`), many small commits, **no `Co-Authored-By` or other AI attribution trailers**.
- All commits go **directly to `main`** (owner's decision, no PRs). Each stage closes with: an "Outcome" section appended to its mini-spec in `docs/specs/` (What / Why / How I steered the agent / How to verify) and an annotated tag `stage-NN` (`git tag -a stage-01 -m "Stage 1: infra"`), pushed with `git push origin main --follow-tags`.
- No PHP or Composer on the host: every PHP command runs in the `api` container (`docker compose exec api …`) or a throwaway `composer:2` container.
- Image targets must build for `linux/arm64` (production) and `linux/amd64` (dev, CI).
- Money in integer cents; tenant-owned tables always carry `tenant_id` (applies from Task 8 on).
- API errors always use the JSON contract `{ "message": string, "code": string, "request_id": string }` (+ `errors` on 422), regardless of the request's `Accept` header.
- Request ids look like `req_` + 8 alphanumeric chars (e.g. `req_4Fh9Kp21`) and are echoed in the `X-Request-Id` header.
- Dev URL: `http://localhost:8088`. Total hosting cost: $0.

## Review Focus

1. **Tenant-owned model created outside an HTTP request** (queue job, seeder, tinker) with no current tenant → must throw `MissingTenantException`, never silently write `tenant_id = null` or leak across tenants. Pinned in Task 8.
2. **Route model binding resolved before the tenant is set** (middleware priority) → a Globex user fetching an Acme ID must get 404, not the record. Pinned in Task 10 (route isolation harness).
3. **Same email in two tenants** (future sandboxes clone the same personas) → must be allowed; uniqueness is per tenant. Pinned in Task 8.
4. **Browser hitting an API URL directly** (no `Accept: application/json`, e.g. a recruiter pasting `/api/invoices/999`) → JSON 404 with the error contract, not an HTML page. Pinned in Task 3.
5. **Redis or MySQL down in production** → `/api/health` answers 503 with JSON naming the failing check, so the deploy health gate fails loudly instead of returning a 500 HTML page. Pinned in Task 3.

---

## Stage 0 — Repo & agent setup

### Task 0: Owner prerequisites (human, not agent)

These unblock Task 7. The agent continues with Tasks 1–6 meanwhile.

- [ ] Create a Groq account and a free API key (no card). Record the free-plan limits from the console's Limits page into a private note (needed in Plan 3).
- [ ] Create an Oracle Cloud account (choose a home region with Ampere A1 availability; it is permanent) and an `VM.Standard.A1.Flex` instance: Ubuntu 24.04 aarch64, 4 OCPU, 24 GB RAM, public IPv4, your SSH public key.
- [ ] Create a DuckDNS subdomain (e.g. `ledgerlight.duckdns.org`) pointing at the VM's public IP.

### Task 1: Repo conventions and commit-msg hook

**Files:**
- Create: `.editorconfig`, `.gitignore`, `.gitattributes`, `README.md` (skeleton: one-paragraph pitch, "Status: under construction", links to spec and roadmap)
- Create: `.githooks/commit-msg`
- Create: `scripts/setup.sh` (sets `git config core.hooksPath .githooks`)
- Test: `tests/hooks/commit-msg.test.sh`

**Interfaces:**
- Produces: `.githooks/commit-msg <msg-file>` — exit 0 when valid, exit 1 with a one-line reason on stderr otherwise. `scripts/setup.sh` is the documented first command after cloning.

- [ ] **Step 1: Write the failing test** `tests/hooks/commit-msg.test.sh` — a plain bash script (no bats) with `pass`/`fail` helpers that writes each message to a temp file, runs the hook, and checks the exit code:

```bash
expect_ok   "feat(api): add health endpoint"
expect_ok   "docs: add product design spec and UI reference"
expect_ok   $'fix(web): handle empty inbox\n\nLonger body explaining why.'
expect_fail "Added stuff"                                   # not conventional
expect_fail "feat: Add thing."                              # subject capitalized / trailing period
expect_fail $'feat(api): add x\n\nCo-Authored-By: Claude <noreply@anthropic.com>'
expect_fail $'feat(api): add x\n\nco-authored-by: Someone <a@b.c>'   # case-insensitive
expect_fail $'feat(api): add x\n\nGenerated with Claude Code'
```
Allowed types: `feat fix docs style refactor perf test build ci chore revert`; scope optional, lowercase `[a-z0-9-]+`; subject starts lowercase, no trailing period, header ≤ 72 chars. Merge commits (`Merge …`) are allowed.

- [ ] **Step 2: Run it** — `bash tests/hooks/commit-msg.test.sh` → FAIL (hook missing).
- [ ] **Step 3: Implement `.githooks/commit-msg`** (POSIX sh + `grep -Ei`), make it executable, and `scripts/setup.sh`.
- [ ] **Step 4: Run** `bash tests/hooks/commit-msg.test.sh` → all PASS; run `bash scripts/setup.sh`.
- [ ] **Step 5: Commit** on `main`:

```bash
git add .editorconfig .gitignore .gitattributes README.md .githooks scripts tests/hooks
git commit -m "chore: add repo conventions and commit-msg hook"
```

### Task 2: Agent rules, guard hooks and format hook

**Files:**
- Create: `CLAUDE.md` (project map, commands, the Global Constraints above, "how we work": spec in `docs/specs/NN-*.md` before code, TDD, small commits)
- Create: `.claude/rules/backend.md` (frontmatter `paths: ["api/**"]`: tenancy rules — every tenant-owned model uses `BelongsToTenant`, every tenant-owned FK uses `tenantForeign`, queue jobs set the tenant explicitly; error contract; Pest conventions)
- Create: `.claude/rules/frontend.md` (`paths: ["web/**"]`: tokens from the design PDF p.1 only, components in `components/ui/`, no hard-coded colors)
- Create: `.claude/rules/testing.md` (no `Http` calls to real services in tests; Groq and Tallyline always faked)
- Create: `.claude/settings.json` (hooks wiring below)
- Create: `.claude/hooks/guard-bash.sh`, `.claude/hooks/guard-files.sh`, `.claude/hooks/format-file.sh`
- Create: `AGENTS.md` (short; points other agents to `CLAUDE.md` and `.claude/rules/`)
- Create: `docs/ai-workflow.md` (skeleton: how specs, rules, hooks and reviews are used; a "Corrections log" table: date · stage · what the agent did · what I changed · why)
- Create: `docs/specs/00-agent-setup.md` (this stage's mini-spec, 10–20 lines)
- Test: `tests/hooks/claude-hooks.test.sh`

**Interfaces:**
- Produces: hook scripts reading Claude Code hook JSON on stdin (`.tool_input.command`, `.tool_input.file_path`) via `jq`; block by exiting **2** with the reason on stderr; allow by exiting 0.

`.claude/settings.json` wiring:
```json
{
  "hooks": {
    "PreToolUse": [
      { "matcher": "Bash", "hooks": [{ "type": "command", "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-bash.sh" }] },
      { "matcher": "Edit|Write|MultiEdit", "hooks": [{ "type": "command", "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/guard-files.sh" }] }
    ],
    "PostToolUse": [
      { "matcher": "Edit|Write|MultiEdit", "hooks": [{ "type": "command", "command": "\"$CLAUDE_PROJECT_DIR\"/.claude/hooks/format-file.sh" }] }
    ]
  }
}
```

- [ ] **Step 1: Write the failing test** `tests/hooks/claude-hooks.test.sh` feeding JSON payloads to each script:

```bash
# guard-bash: blocks (exit 2)
expect_block bash '{"tool_input":{"command":"git commit --no-verify -m \"feat: x\""}}'
expect_block bash '{"tool_input":{"command":"git commit -m \"feat: x\" -m \"Co-Authored-By: Claude <noreply@anthropic.com>\""}}'
expect_block bash '{"tool_input":{"command":"git push --force origin main"}}'
expect_block bash '{"tool_input":{"command":"git push -f origin main"}}'
# guard-bash: allows
expect_allow bash '{"tool_input":{"command":"git commit -m \"feat(api): add health\""}}'
expect_allow bash '{"tool_input":{"command":"git push --force-with-lease origin stage/01-infra"}}'

# guard-files: blocks
expect_block files '{"tool_input":{"file_path":"'"$ROOT"'/api/.env"}}'
expect_block files '{"tool_input":{"file_path":"'"$ROOT"'/.env.production"}}'
expect_block files '{"tool_input":{"file_path":"'"$ROOT"'/api/database/migrations/0001_01_01_000000_create_users_table.php"}}'  # exists on origin/main
# guard-files: allows
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/api/.env.example"}}'
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/api/database/migrations/2026_11_20_000000_new_migration.php"}}'  # not on origin/main
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/README.md"}}'

# format-file: never blocks, even when containers are down
expect_allow format '{"tool_input":{"file_path":"'"$ROOT"'/api/app/Models/User.php"}}'
```
The migration rule: a file under `api/database/migrations/` that exists on `origin/main` (`git cat-file -e origin/main:<relpath>`) is immutable — message: `Migration already merged to main; create a new migration instead.` The migration test that needs a file on `origin/main` is skipped (printed as SKIP) until Task 3 merges one.

- [ ] **Step 2: Run** `bash tests/hooks/claude-hooks.test.sh` → FAIL.
- [ ] **Step 3: Implement the three scripts.** `format-file.sh`: for `api/**/*.php` run `docker compose exec -T api vendor/bin/pint <path-relative-to-api>` only if `docker compose ps --status running api` lists it; for `web/**/*.{ts,vue,js}` run `pnpm --dir web exec eslint --fix <path>` only if `web/node_modules` exists; always exit 0.
- [ ] **Step 4: Run** the hook tests → PASS. Write `CLAUDE.md`, rules, `AGENTS.md`, `docs/ai-workflow.md`, `docs/specs/00-agent-setup.md`.
- [ ] **Step 5: Commit** in small commits, e.g. `feat(agent): add guard and format hooks`, `docs(agent): add project rules and claude md`, `docs: add ai workflow log`. Append the Outcome section to `docs/specs/00-agent-setup.md`, tag `stage-00`, push `main` with tags.

---

## Stage 1 — Infra

### Task 3: Laravel API in Docker with health check and error contract

**Files:**
- Create: `api/` via `docker run --rm -v "$PWD":/app -w /app composer:2 create-project laravel/laravel api --ignore-platform-reqs` (then `composer require laravel/sanctum` + `pestphp/pest` per Laravel 13 docs; remove PHPUnit example tests)
- Create: `docker/php/Dockerfile` (`FROM dunglas/frankenphp:php8.5-alpine`; `install-php-extensions pdo_mysql redis pcntl intl zip bcmath opcache`; composer from `composer:2`; targets `dev` and `prod`)
- Create: `docker/mysql/init/01-test-db.sql` (creates `ledgerlight_test`, grants to the app user)
- Create: `compose.yaml` (services `api`, `mysql:8.4`, `redis:8-alpine`; named volumes; healthchecks)
- Create: `api/app/Http/Controllers/HealthController.php`
- Create: `api/app/Http/Middleware/AssignRequestId.php`
- Modify: `api/bootstrap/app.php` (API routes, prepend `AssignRequestId` globally, `shouldRenderJsonWhen` for `api/*`, render the error contract)
- Create: `api/app/Support/ErrorCode.php` (string-backed enum: `not_found`, `forbidden`, `unauthenticated`, `validation_failed`, `server_error`, `service_unavailable`)
- Test: `api/tests/Feature/HealthTest.php`, `api/tests/Feature/ErrorContractTest.php`
- Create: `Makefile` (`up`, `down`, `sh`, `test`, `test-api`, `lint`, `fmt`, `migrate`)

**Interfaces:**
- Produces: `GET /api/health` → `200 {"status":"ok","checks":{"database":"ok","redis":"ok"}}` or `503 {"status":"fail","checks":{…:"fail"}}`. Header `X-Request-Id` on every response. `App\Support\ErrorCode` used by all later error responses. Tests use the `ledgerlight_test` MySQL database (`phpunit.xml` env `DB_DATABASE=ledgerlight_test`), never SQLite.

- [ ] **Step 1: Scaffold** the app, Dockerfile, compose and Makefile; `make up` → all three containers healthy; `docker compose exec api php artisan --version` prints `Laravel Framework 13.x`.
- [ ] **Step 2: Write the failing tests:**

```php
// HealthTest.php
it('reports ok when database and redis respond', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]);
});

it('returns 503 json naming the failing check when redis is down', function () {
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('down'));
    $this->get('/api/health')                       // no Accept header on purpose
        ->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.redis', 'fail')
        ->assertJsonPath('checks.database', 'ok');
});

// ErrorContractTest.php
it('renders unknown api routes as the json error contract even without accept header', function () {
    $res = $this->get('/api/does-not-exist');
    $res->assertNotFound()->assertJsonStructure(['message', 'code', 'request_id'])
        ->assertJsonPath('code', 'not_found');
    expect($res->json('request_id'))->toMatch('/^req_[A-Za-z0-9]{8}$/')
        ->toBe($res->headers->get('X-Request-Id'));
});

it('echoes a valid incoming request id', function () {
    $this->getJson('/api/health', ['X-Request-Id' => 'req_AbCd1234'])
        ->assertHeader('X-Request-Id', 'req_AbCd1234');
});

it('replaces a malformed incoming request id', function () {
    $id = $this->getJson('/api/health', ['X-Request-Id' => '<script>'])->headers->get('X-Request-Id');
    expect($id)->toMatch('/^req_[A-Za-z0-9]{8}$/');
});
```

- [ ] **Step 3: Run** `make test-api` → FAIL.
- [ ] **Step 4: Implement** `HealthController` (DB: `DB::select('select 1')`; Redis: `Redis::connection()->ping()`; each wrapped in try/catch), `AssignRequestId` (stores the id in `Context::add('request_id', …)` so logs carry it), and the exception rendering in `bootstrap/app.php` mapping 404/405 → `not_found`, 403 → `forbidden`, 401 → `unauthenticated`, 422 → `validation_failed` (+`errors`), 503 → `service_unavailable`, other → `server_error` (message "Something went wrong." in production; real message only when `app.debug`).
- [ ] **Step 5: Run** `make test-api` → PASS.
- [ ] **Step 6: Commit**: `build(api): scaffold laravel 13 on frankenphp`, `build: add compose stack with mysql and redis`, `feat(api): add health endpoint`, `feat(api): add request ids and json error contract`.

### Task 4: Nuxt app and Caddy edge

**Files:**
- Create: `web/` via `pnpm dlx nuxi@latest init web --packageManager pnpm`; add `tailwindcss` + `@tailwindcss/vite`, `@nuxt/eslint`, `@nuxt/test-utils`, `vitest`, `@vue/test-utils`, `happy-dom`
- Create: `web/app/assets/css/main.css` (`@import "tailwindcss";` only — tokens arrive in Plan 2)
- Create: `web/app/pages/index.vue` (placeholder: "Ledgerlight" + API status fetched from `/api/health`)
- Create: `web/app/composables/useApiHealth.ts`
- Create: `docker/web/Dockerfile` (node:22-alpine, corepack pnpm; `dev` target runs `nuxt dev --host`, `prod` target runs `.output/server/index.mjs`)
- Create: `docker/caddy/Caddyfile` (site address from `{$APP_ADDRESS::8080}`; `handle /api/* /sanctum/* /up` → `api:8000`; everything else → `web:3000`)
- Modify: `compose.yaml` (add `web`, `caddy`; only `caddy` publishes a port: `8088:8080` (host port, overridable via `LEDGERLIGHT_HTTP_PORT`))
- Test: `web/tests/unit/useApiHealth.test.ts`

**Interfaces:**
- Consumes: `GET /api/health` (Task 3).
- Produces: `useApiHealth(): { status: Ref<'ok' | 'fail' | 'unknown'> }`. Single origin `http://localhost:8088` for both apps. Nuxt runtime config key `apiInternalBase` (`http://api:8000`) for server-side fetches.

- [ ] **Step 1: Write the failing test** — `useApiHealth` maps a 200 `{status:'ok'}` to `'ok'`, a 503 to `'fail'`, and a network error to `'unknown'` (mock `$fetch` with `registerEndpoint`/`vi.stubGlobal`).
- [ ] **Step 2: Run** `pnpm --dir web vitest run` → FAIL.
- [ ] **Step 3: Implement** the composable and page; Dockerfile; Caddyfile; compose services.
- [ ] **Step 4: Verify** `pnpm --dir web vitest run` → PASS; `make up`; `curl -s localhost:8088/api/health` → `{"status":"ok",…}`; `curl -s localhost:8088/ | grep -o Ledgerlight` → `Ledgerlight`; HMR works when editing `index.vue`.
- [ ] **Step 5: Commit**: `build(web): scaffold nuxt 4 with tailwind 4`, `feat(web): show api health on placeholder page`, `build: add caddy edge serving web and api on one origin`.

### Task 5: Static analysis and formatting

**Files:**
- Create: `api/phpstan.neon` (Larastan, level 8, paths `app`, `routes`, `database`)
- Create: `api/pint.json` (`laravel` preset)
- Create: `api/tests/ArchTest.php`
- Create: `web/eslint.config.mjs` (from `@nuxt/eslint`)
- Modify: `Makefile` (`lint`: pint --test, phpstan, eslint, `nuxi typecheck`; `fmt`: pint, eslint --fix)

**Interfaces:**
- Produces: `make lint` and `make test` as the single commands CI and agents run.

- [ ] **Step 1: Write the arch tests** (they must pass now and guard later work):

```php
arch()->preset()->php();
arch()->preset()->security();
arch('no debug helpers')->expect(['dd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();
arch('controllers stay thin')->expect('App\Http\Controllers')->not->toUse('Illuminate\Support\Facades\DB');
```
- [ ] **Step 2: Run** `make lint && make test` → fix every finding until green.
- [ ] **Step 3: Commit**: `chore(api): add larastan pint and arch tests`, `chore(web): add eslint and typecheck`.

### Task 6: CI on GitHub Actions

**Files:**
- Create: `.github/workflows/ci.yml`

**Interfaces:**
- Produces: jobs `api`, `web`, `hooks` on every push to `main`.

Jobs:
- `api` (ubuntu-24.04): services `mysql:8.4` (db `ledgerlight_test`) and `redis:8-alpine`; `shivammathur/setup-php` with PHP 8.5 and the Task 3 extensions; `composer install`; `vendor/bin/pint --test`; `vendor/bin/phpstan`; `vendor/bin/pest --ci`.
- `web` (ubuntu-24.04): `pnpm/action-setup`, Node 22, `pnpm install --frozen-lockfile`, `pnpm lint`, `pnpm typecheck`, `pnpm vitest run`.
- `hooks`: `bash tests/hooks/commit-msg.test.sh && bash tests/hooks/claude-hooks.test.sh`.

- [ ] **Step 1:** Write the workflow; commit; push `main`.
- [ ] **Step 2: Verify** the run is green through the public API (no `gh` installed): `curl -s "https://api.github.com/repos/Hugueninfer/ledgerlight/actions/runs?branch=main&per_page=1" | jq -r '.workflow_runs[0] | .status + " " + (.conclusion // "")'` → `completed success`. On failure, read the job logs in the browser and fix.
- [ ] **Step 3: Commit**: `ci: add lint and test workflow`.

### Task 7: Production images and hello deploy

Blocked on Task 0 (VM, DuckDNS). Until then, do Steps 1–2 and continue with Stage 2; return here when the VM exists.

**Files:**
- Modify: `docker/php/Dockerfile`, `docker/web/Dockerfile` (`prod` targets: no dev deps, opcache on, non-root user)
- Create: `compose.prod.yaml` (overlay: images `ghcr.io/hugueninfer/ledgerlight-api:${TAG}` / `-web`, no bind mounts, `restart: unless-stopped`, Caddy publishes 80/443 with `APP_ADDRESS=${APP_DOMAIN}` for automatic HTTPS, volumes `caddy_data`, `mysql_data`, `storage`)
- Create: `.github/workflows/deploy.yml`
- Create: `docs/deploy.md` (runbook)
- Create: `scripts/deploy.sh` (runs on the VM: pull, `up -d`, `php artisan migrate --force`, `php artisan optimize`, then `curl -fsS https://$APP_DOMAIN/api/health`)

**Interfaces:**
- Consumes: CI workflow (Task 6) — deploy runs only after `ci.yml` succeeds on `main` (`workflow_run`).
- Produces: images tagged with the commit SHA and `latest`; live URL `https://<duckdns-host>`.

`deploy.yml`: build job on `ubuntu-24.04-arm` (native arm64 runner, free for public repos) with `docker/build-push-action`, push to GHCR with `GITHUB_TOKEN`; deploy job uses `appleboy/ssh-action` (secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`) to run `TAG=<sha> /opt/ledgerlight/scripts/deploy.sh`; fails the workflow if the health check fails.

`docs/deploy.md` must cover, in order: installing Docker on Ubuntu 24.04 aarch64; **opening ports 80/443 in both the OCI VCN security list and the VM's iptables** (Oracle Ubuntu images ship REJECT rules — insert ACCEPT rules before them and persist with `netfilter-persistent save`); DuckDNS update cron; creating `/opt/ledgerlight/.env` (never committed; `APP_KEY`, DB passwords, `APP_DOMAIN`); making the GHCR packages public; adding the three repository secrets in GitHub → Settings → Secrets.

- [ ] **Step 1:** Build prod images locally for arm64 to catch issues early: `docker buildx build --platform linux/arm64 --target prod -f docker/php/Dockerfile api` → succeeds.
- [ ] **Step 2:** Run the prod overlay locally with `APP_DOMAIN=localhost`: `docker compose -f compose.yaml -f compose.prod.yaml up -d` → `curl -k https://localhost/api/health` → `"status":"ok"`.
- [ ] **Step 3:** Owner follows `docs/deploy.md` on the VM (agent guides; agent never handles the VM's private keys or `.env` secrets).
- [ ] **Step 4:** Push `main`; watch `deploy.yml` → green (same `curl` + `jq` check, filtered by `workflow_id=deploy.yml`). **Verify** from outside: `curl -s https://<host>/api/health` → `{"status":"ok",…}` and the placeholder page loads over HTTPS.
- [ ] **Step 5: Commit**, then append Stage 1's Outcome, tag `stage-01`, push with tags: `build: add production images and compose overlay`, `ci: deploy to oracle vm on main`, `docs: add deploy runbook`.

---

## Stage 2 — Auth & tenancy

Write `docs/specs/02-tenancy.md` first (mini-spec: the decisions in this section, 15–25 lines).

### Task 8: Tenancy core — schema, macros, scope, current tenant

**Files:**
- Create: `api/app/Tenancy/CurrentTenant.php`
- Create: `api/app/Tenancy/BelongsToTenant.php` (trait)
- Create: `api/app/Tenancy/TenantScope.php`
- Create: `api/app/Tenancy/MissingTenantException.php`
- Create: `api/app/Providers/TenancyServiceProvider.php` (registers `CurrentTenant` as scoped singleton and the Blueprint macros)
- Create: `api/app/Models/Tenant.php`, `api/app/Enums/Role.php`, `api/app/Models/Invitation.php`
- Modify: `api/app/Models/User.php` (uses `BelongsToTenant`; `role` cast to `Role`; no password)
- Create migrations: `create_tenants_table`; modify the default `create_users_table` **before its first merge** (add `tenant_id`, `role`; make `password` nullable; drop global unique on `email`); `create_invitations_table`
- Create: `api/database/factories/TenantFactory.php`, `InvitationFactory.php`; modify `UserFactory.php`
- Test: `api/tests/Feature/Tenancy/TenantScopeTest.php`, `api/tests/Feature/Tenancy/CompositeForeignKeyTest.php`

**Interfaces:**
- Produces:
  - `CurrentTenant::set(Tenant $tenant): void`, `get(): ?Tenant`, `id(): ?int`, `forget(): void`, `run(Tenant $tenant, callable $callback): mixed` (sets, runs, restores — for jobs/seeders).
  - Trait `BelongsToTenant`: adds `TenantScope` (filters by `CurrentTenant::id()` when set), fills `tenant_id` on `creating` from `CurrentTenant`, throws `MissingTenantException` when neither is present; relation `tenant(): BelongsTo`.
  - Blueprint macros: `$table->tenantKey()` → `unique(['tenant_id', 'id'])`; `$table->tenantForeign(string $column, string $table)` → composite FK `[tenant_id, $column] → $table(tenant_id, id)`.
  - `enum Role: string { case Uploader = 'uploader'; case Reviewer = 'reviewer'; case Admin = 'admin'; }` with `atLeast(Role $other): bool` (order Uploader < Reviewer < Admin) and `label(): string`.
  - Tables: `tenants(id, slug unique, name, brand_color, timestamps)`; `users(id, tenant_id FK, name, email, role, password nullable, remember_token, timestamps, unique(tenant_id,email), tenantKey)`; `invitations(id, tenant_id, email, role, invited_by_user_id, accepted_at null, expires_at, timestamps, tenantForeign('invited_by_user_id','users'), unique(tenant_id,email))`.

- [ ] **Step 1: Write the failing tests:**

```php
// TenantScopeTest.php
it('only returns rows of the current tenant', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    User::factory()->for($acme)->count(2)->create();
    User::factory()->for($globex)->create();
    app(CurrentTenant::class)->set($globex);
    expect(User::count())->toBe(1);
});

it('fills tenant_id from the current tenant on create', function () {
    $acme = Tenant::factory()->create();
    app(CurrentTenant::class)->run($acme, fn () => Invitation::factory()->create(['tenant_id' => null]));
    expect(Invitation::withoutGlobalScopes()->first()->tenant_id)->toBe($acme->id);
});

it('refuses to create a tenant-owned row without a tenant', function () {
    app(CurrentTenant::class)->forget();
    User::factory()->make(['tenant_id' => null])->save();
})->throws(MissingTenantException::class);

it('allows the same email in two tenants but not twice in one', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    User::factory()->for($acme)->create(['email' => 'priya@acmelogistics.com']);
    User::factory()->for($globex)->create(['email' => 'priya@acmelogistics.com']);
    expect(fn () => User::factory()->for($acme)->create(['email' => 'priya@acmelogistics.com']))
        ->toThrow(UniqueConstraintViolationException::class);
});

// CompositeForeignKeyTest.php
it('rejects an invitation whose inviter belongs to another tenant at the database level', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $globexAdmin = User::factory()->for($globex)->create(['role' => Role::Admin]);
    expect(fn () => DB::table('invitations')->insert([
        'tenant_id' => $acme->id, 'email' => 'x@acme.test', 'role' => 'reviewer',
        'invited_by_user_id' => $globexAdmin->id, 'expires_at' => now()->addDays(7),
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);   // raw insert bypasses Eloquent on purpose
});

it('orders roles uploader < reviewer < admin', function () {
    expect(Role::Admin->atLeast(Role::Reviewer))->toBeTrue()
        ->and(Role::Uploader->atLeast(Role::Reviewer))->toBeFalse();
});
```
- [ ] **Step 2: Run** `make test-api` → FAIL.
- [ ] **Step 3: Implement** the classes, macros, migrations, factories as specified in Interfaces. Add to `tests/ArchTest.php`: every class in `App\Models` except `Tenant` uses `App\Tenancy\BelongsToTenant`.
- [ ] **Step 4: Run** `make test-api && make lint` → PASS.
- [ ] **Step 5: Commit**: `feat(api): add tenants and roles`, `feat(api): add tenant scope and current tenant`, `feat(api): add composite tenant foreign keys`, `test(api): prove cross-tenant references fail in mysql`.

### Task 9: Sanctum SPA session and demo persona login

**Files:**
- Create: `api/config/demo.php` (personas)
- Create: `api/database/seeders/DemoTenantsSeeder.php` (called from `DatabaseSeeder`)
- Create: `api/app/Http/Controllers/DemoSessionController.php` (`store`, `destroy`)
- Create: `api/app/Http/Controllers/MeController.php`
- Create: `api/app/Http/Middleware/SetCurrentTenant.php`
- Create: `api/app/Http/Resources/MeResource.php`
- Modify: `api/bootstrap/app.php` (`statefulApi()`; register `SetCurrentTenant` in the `api` group and **prepend it to the middleware priority list before `SubstituteBindings`**), `api/routes/api.php`
- Modify: `api/.env.example` (`SANCTUM_STATEFUL_DOMAINS=localhost:8088`, `SESSION_DOMAIN=localhost`)
- Test: `api/tests/Feature/Auth/DemoSessionTest.php`, `api/tests/Feature/Auth/MeTest.php`

**Interfaces:**
- Consumes: `CurrentTenant`, `Role`, `Tenant`, `User` (Task 8).
- Produces:
  - `config('demo.personas')`: `acme-uploader` → maya@acmelogistics.com, `acme-reviewer` → jordan@acmelogistics.com, `acme-admin` → priya@acmelogistics.com, `globex-admin` → sam@globexfoods.com.
  - `POST /api/demo/sessions {persona}` → 201 + `MeResource`; regenerates the session. Plan 7 swaps the internals for sandbox cloning; the route and payload stay.
  - `DELETE /api/demo/sessions/current` → 204.
  - `GET /api/me` → `{ user: {id, name, email, role, initials}, tenant: {id, name, slug, brand_color, member_count} }`.
  - Seed data (from the design PDF): **Acme Logistics** (slug `acme`, brand `#1D5FC4`) — Priya Natarajan (admin), Jordan Reyes (reviewer), Dana Whitfield (reviewer), Maya Chen (uploader), Alex Romero (uploader), pending invitation tom.becker@acmelogistics.com (reviewer, invited by Priya); **Globex Foods** (slug `globex`, brand `#16794A`) — Sam Okafor (admin).

- [ ] **Step 1: Write the failing tests:**

```php
beforeEach(fn () => $this->seed(DemoTenantsSeeder::class));

it('signs in as a persona and returns me', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'acme-reviewer'])
        ->assertCreated()
        ->assertJsonPath('user.name', 'Jordan Reyes')
        ->assertJsonPath('user.role', 'reviewer')
        ->assertJsonPath('user.initials', 'JR')
        ->assertJsonPath('tenant.name', 'Acme Logistics')
        ->assertJsonPath('tenant.member_count', 5);
    $this->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'jordan@acmelogistics.com');
});

it('rejects an unknown persona with the validation contract', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'root'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('persona');
});

it('requires authentication for me', function () {
    $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('signs out', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'globex-admin']);
    $this->deleteJson('/api/demo/sessions/current')->assertNoContent();
    $this->getJson('/api/me')->assertUnauthorized();
});
```
(`member_count` counts users, not pending invitations → 5.)
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** per Interfaces. `SetCurrentTenant` sets `CurrentTenant` from `$request->user()->tenant` when authenticated.
- [ ] **Step 4: Run** `make test-api` → PASS. Manual check through Caddy: `curl -c j -b j localhost:8088/sanctum/csrf-cookie`, then POST with the `X-XSRF-TOKEN` header → 201.
- [ ] **Step 5: Commit**: `feat(api): add demo tenants seeder`, `feat(api): add demo persona sessions with sanctum`, `feat(api): add me endpoint`.

### Task 10: Team endpoints, role gate, and the route isolation harness

**Files:**
- Create: `api/app/Http/Middleware/RequireRole.php` (alias `role`, usage `role:admin`)
- Create: `api/app/Http/Controllers/TeamMemberController.php` (`index`, `show`)
- Create: `api/app/Http/Resources/TeamMemberResource.php`
- Modify: `api/routes/api.php` (`/api/team/members`, `/api/team/members/{member}` with `role:admin`)
- Test: `api/tests/Feature/Team/TeamMemberTest.php`, `api/tests/Feature/Tenancy/RouteIsolationTest.php`

**Interfaces:**
- Consumes: Tasks 8–9.
- Produces:
  - `role:<role>` middleware: 403 with `{message, code:'forbidden', request_id, role, required}` where `role`/`required` are role values. Message example: "Team settings are for admins."
  - `GET /api/team/members` → list of `{id, name, email, role, initials}` ordered admin first, then by name. `GET /api/team/members/{member}` → one.
  - **Route isolation harness** — every later plan registers its route parameters here.

- [ ] **Step 1: Write the failing tests:**

```php
// TeamMemberTest.php
beforeEach(fn () => $this->seed(DemoTenantsSeeder::class));

it('lists only own tenant members for an admin', function () {
    $this->actingAsPersona('acme-admin')->getJson('/api/team/members')
        ->assertOk()->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.name', 'Priya Natarajan');
});

it('forbids non-admins', function () {
    $this->actingAsPersona('acme-uploader')->getJson('/api/team/members')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden')
        ->assertJsonPath('role', 'uploader')
        ->assertJsonPath('required', 'admin');
});

// RouteIsolationTest.php — the harness
// Maps every route parameter name to a factory creating that resource in a given tenant.
const TENANT_OWNED = [
    'member' => fn (Tenant $t) => User::factory()->for($t)->create(),
];

it('never exposes another tenant\'s resources on any parameterized api route', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $intruder = User::factory()->for($globex)->create(['role' => Role::Admin]);

    $routes = collect(Route::getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/') && $r->parameterNames());
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        foreach ($route->parameterNames() as $param) {
            expect(TENANT_OWNED)->toHaveKey($param, "Add a factory for route parameter {{$param}} in RouteIsolationTest so tenant isolation is tested.");
        }
        $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => app(CurrentTenant::class)->run($acme, fn () => TENANT_OWNED[$m[1]]($acme))->getRouteKey(), $route->uri());
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $this->actingAs($intruder)->json($method, '/'.$uri)
                ->assertNotFound("{$method} /{$uri} leaked across tenants");
        }
    }
});
```
Add the `actingAsPersona(string $persona): static` helper to `tests/Pest.php` (looks up the persona email across tenants without the scope and calls `actingAs`).
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the middleware, controller, resource and routes.
- [ ] **Step 4: Run** `make test-api && make lint` → PASS. Then **prove the harness bites**: temporarily remove `SetCurrentTenant` from the priority list, run `RouteIsolationTest` → it must FAIL with "leaked across tenants"; restore → PASS. Record this in `docs/ai-workflow.md`'s corrections log if the agent's first implementation got the ordering wrong.
- [ ] **Step 5: Commit**: `feat(api): add role middleware with forbidden contract`, `feat(api): add team member endpoints`, `test(api): add route isolation harness`. Append the Outcome to `docs/specs/02-tenancy.md`, tag `stage-02`, push with tags → CI green → deploy runs → `curl https://<host>/api/health` still ok.

---

## Done when

- `main` is deployed at `https://<duckdns-host>` and `/api/health` answers ok.
- `make lint && make test` and the hook tests pass locally and in CI.
- Tags `stage-00`, `stage-01`, `stage-02` on `main`, each stage's mini-spec ending with its Outcome section.
- No commit on `main` contains an AI co-author trailer: `git log main --format=%B | grep -ci co-authored-by` → `0`.
