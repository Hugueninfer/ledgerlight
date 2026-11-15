---
paths: ["api/**"]
---

# Backend rules (Laravel API)

## Tenancy (design spec section 9; the tenancy code lands in stage 2)

- (Introduced in stage 2.) Every tenant-owned model uses the `BelongsToTenant` trait (global scope). The tenant comes from the authenticated user, never from the request.
- Every tenant-owned table has `tenant_id`, a unique key `(tenant_id, id)`, and every foreign key to another tenant-owned table is created with the `tenantForeign` macro (added in stage 2), which emits the composite key `(tenant_id, x_id) -> x(tenant_id, id)`. A plain `foreignId()->constrained()` on a tenant-owned table is a bug.
- Queue jobs carry the tenant explicitly (constructor argument) and set it before touching tenant-owned models. Never rely on an ambient "current user" inside a job.
- Route model binding resolves inside the tenant scope, so another tenant's ids return 404.
- Validation rules `exists:` and `unique:` and writes through `DB::table()` / the query builder bypass the tenant scope and the model guards. Use `Rule::exists(...)->where('tenant_id', ...)` / `Rule::unique(...)->where('tenant_id', ...)` or scoped Eloquent instead.
- Code running outside an HTTP request (jobs, commands, seeders) must set the tenant with `CurrentTenant::run()` before it reads or writes tenant-owned models.
- Known gap: the tenant scope currently fails open (it applies no filter when no tenant is current). Plan 2 Task 1 makes it fail closed; until then, never rely on "no tenant" meaning "no rows".
- Policies cover every action per role. A new route is not done until the tenancy isolation suite covers it.

## Error contract

- Every error response is JSON `{ "message", "code", "request_id" }` (+ `errors` on 422), whatever the `Accept` header says. `code` is a stable snake_case machine string.
- `request_id` is `req_` + 8 alphanumeric chars and matches the `X-Request-Id` response header.
- Do not leak exception messages or stack traces on 5xx.
- `ApiException` is the only way to return a custom error message or extra fields; any other exception renders with the fixed message of its `ErrorCode`. Never pass exception text, user input or secrets into it.

## Data

- Money is integer cents. Never use floats for amounts.
- Migrations that are on `origin/main` are immutable (a hook enforces it): add a new migration instead.
- Run PHP in the container: `docker compose exec api ...`.

## Pest conventions

- Feature tests per endpoint; unit tests for pure logic (confidence, circuit breaker).
- Name tests as behaviour: `it('returns 404 for another tenant\'s invoice')`.
- Create data with factories; make the tenant explicit in the test (`actingAs($userOfTenantB)`).
- Fake external services (see `testing.md`). Format with Pint before committing.
