# Stage 2: Auth & tenancy

## Goal
Two companies share one database and can never see or reference each other's data, and the database itself enforces it, not just the application.

## Decisions
- The current tenant is resolved from the authenticated user, never from the request (no header, subdomain or body field).
- Every tenant-owned table carries `tenant_id`; a `BelongsToTenant` trait adds a global scope and fills `tenant_id` on create from `CurrentTenant` (a scoped singleton, reset per request and job).
- MySQL has no row-level security, so the database guarantee is composite keys: every tenant-owned table has `unique(tenant_id, id)`, and every reference to another tenant-owned row is a composite foreign key `(tenant_id, x_id) -> x(tenant_id, id)` (`tenantKey()` / `tenantForeign()` Blueprint macros).
- Creating a tenant-owned row with no tenant (job, seeder, tinker) throws `MissingTenantException`; it never writes `tenant_id = null` silently.
- Email is unique per tenant, not globally: demo sandboxes clone the same personas into several tenants.
- Demo personas (Acme Logistics: uploader, reviewer, admin; Globex Foods: admin) sign in with Sanctum SPA cookie auth on one origin; no passwords.
- A wrong role gets 403 with the error contract (`code: forbidden`); another tenant's resource gets 404, because route model binding runs inside the tenant scope.
- A route isolation harness iterates every parameterised API route as a Globex user against Acme resources and fails if a route has no factory registered.

## Acceptance checks
- `make lint && make test` pass; the arch suite fails if a model other than `Tenant` lacks `BelongsToTenant`.
- A raw insert of an Acme invitation whose inviter is a Globex user is rejected by MySQL.
- `POST /api/demo/sessions` signs in as a persona; `GET /api/me` returns the user and tenant.
- The route isolation harness fails when `SetCurrentTenant` is removed from the middleware priority list.

## Outcome

**Status.** Live deploy still pending: waiting for the Oracle VM (see docs/deploy.md).

**What.** Tenants and roles; a `BelongsToTenant` trait (global scope plus create/update guards) driven by a scoped `CurrentTenant`; composite `(tenant_id, x_id)` foreign keys via the `tenantKey()` / `tenantForeign()` macros; Acme and Globex demo tenants with password-less persona sign-in over Sanctum cookie sessions; `GET /api/me`; admin-only team endpoints behind a `role:<role>` gate that answers 403 with the user's and the required role; and a route isolation harness that calls every parameterised API route as a Globex admin against Acme resources.

**Why.** Tenant isolation is the headline promise. Isolation is enforced by the scope, and cross-tenant references are rejected by the database itself. The harness tests it on every parameterised route, not just on the routes someone remembered to test; list routes rely on the scope plus their own feature tests (see Known follow-ups).

**How I steered the agent.**
- `tenant_id` could be set to another tenant explicitly on create, or changed on update; both now throw `TenantMismatchException`.
- The cross-tenant foreign key test passed even with the table missing (any exception counted); it now does a same-tenant control insert first and asserts SQLSTATE 23000 / MySQL errno 1452.
- The users table migration was already merged, so the tenancy columns went into a new alter migration instead of editing it.
- `InvitationFactory` picked an inviter from any tenant; it now creates the inviter in the invitation's own tenant.
- Personas were looked up by email alone (ambiguous, emails are unique per tenant); now tenant slug + email. Persona validation uses `Rule::in` instead of database rules, which bypass Eloquent scopes.
- `SetCurrentTenant`'s positive path and its order before route model binding were untested; both are pinned, with mutation checks.
- Demo sign-in from a non-browser client (no cookie session) returned 500; it is now a 400 with the error contract.
- Logout's CSRF token regeneration is pinned by a test.
- The error handler only renders fixed per-code messages, so the role gate's body could not carry its own message and fields; `ApiException` is now the single, explicit opt-in for that, tested directly.
- Test helpers were global functions in test files (one Pest process, so a later file could hit "cannot redeclare"); they now live in `Tests\Support`, and the harness registry is `Tests\Support\TenantIsolation::ROUTE_PARAMETERS`.

**Caught during implementation.** The agent found and fixed these itself; listed here so the attribution is honest.
- The role gate runs before route model binding (priority list), so a non-admin gets 403 for any id and cannot probe which ones exist; mutation-checked.
- The planned harness assertions silently dropped their failure messages (`assertNotFound()` takes no message; the second argument of `toHaveKey()` is the expected value, so it would always fail); rewritten so a failure names the leaking route.
- The harness got an owner control (the Acme admin must not get 404 on the same URI), so a route that 404s for everyone cannot pass vacuously, and it clears the current tenant before each request, since the test app reuses one container.
- The harness was proven to bite, as the acceptance checks require: with `SetCurrentTenant` removed from the middleware priority list it fails with `GET /api/team/members/3 leaked across tenants (answered 200 ...)`; restored, it passes.

**Known follow-ups.**
- The harness covers parameterised routes only. List routes (like `GET /api/team/members`) depend on the tenant scope, which fails open when no tenant is current; today each one has its own cross-tenant feature test, but nothing generic checks future list routes yet.
- Nested routes with scoped bindings and public parameterised routes (e.g. an invitation token) will need harness support when they arrive.

**How to verify.**
```bash
make lint && make test
docker compose exec api vendor/bin/pest tests/Feature/Tenancy
# the harness bites: comment out the prependToPriorityList(... SetCurrentTenant ...) line
# in api/bootstrap/app.php, rerun RouteIsolationTest, see "leaked across tenants", restore
docker compose exec api vendor/bin/pest tests/Feature/Auth tests/Feature/Team
```
CI: see the badge in the README or https://github.com/Hugueninfer/ledgerlight/actions/workflows/ci.yml
