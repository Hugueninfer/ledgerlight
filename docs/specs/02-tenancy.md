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
