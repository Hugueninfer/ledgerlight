# Stage 1: Infra

## Goal
One command brings up the whole stack locally, CI proves it, and the same images deploy to an arm64 VM at no cost.

## Decisions
- API runs on FrankenPHP on :8000 as a non-root user; the prod image never bakes `api/.env`.
- Single origin: Caddy fronts web and API (`/api/*` to the API), so there is no CORS and cookies stay first-party. Dev edge on :8088.
- MySQL and Redis are not published to the host; only the edge is reachable.
- JSON error contract: every error has `code`, `message` and a request id. Codes include `bad_request`, `conflict`, `csrf_mismatch`, `too_many_requests`, `server_error`. Headers such as `Retry-After` and `Allow` are preserved.
- Trusted proxies configured behind Caddy so client IPs and scheme are right, covered by a test.
- CI jobs: lint, API tests (`APP_ENV=testing`, architecture rules), web checks, image builds.
- Images are built for arm64 on GitHub's `ubuntu-24.04-arm` runners and pushed to GHCR (SHA and `latest` tags).
- Deploy runs via `workflow_run` after CI, gated to same-repo `push` events on `main`; SSH step uses a pinned action SHA, a host-key fingerprint (fail closed) and minimal permissions.

## Acceptance checks
- `make setup && make up` works on a fresh clone; `curl -s localhost:8088/api/health` returns ok.
- `make lint && make test` pass; CI is green on `main`.
- Deploy is skipped, not failed, until `DEPLOY_HOST` is set.

## Outcome

**Status.** Live deploy pending: waiting for the Oracle VM (see docs/deploy.md).

**What.** A containerised dev and prod stack (FrankenPHP API, web, Caddy edge, MySQL, Redis), a JSON error contract with request ids, CI, arm64 image builds pushed to GHCR, and a gated SSH deploy workflow with a runbook.

**Why.** The later stages need a trustworthy base: reproducible environments, predictable errors for the UI and the agent to rely on, and a deploy path that cannot be triggered or spoofed by outsiders.

**How I steered the agent.**
- The prod image would have baked `api/.env` (no `.dockerignore`); added one.
- Tests ran with `APP_ENV=local`; fixed to run in testing.
- The error responder dropped headers like `Retry-After` and `Allow`; now preserved.
- 419 and 429 were reported as `server_error`; they got dedicated codes (`csrf_mismatch`, `too_many_requests`).
- `make setup` created root-owned mountpoints on a fresh clone; fixed.
- Health status rendered only client-side; now server-rendered via `apiInternalBase`.
- The architecture rule was proven to bite with a throwaway controller that violated it.
- A fork PR from a branch named `main` could have triggered the deploy workflow; gated on same-repo `push` events.
- No trusted proxies behind Caddy; added with a test.
- SSH step hardened: pinned action SHA, host-key fingerprint, minimal permissions (and now fail-closed on an empty fingerprint).

**How to verify.**
```bash
make setup && make up
curl -s localhost:8088/api/health
make lint && make test
```
CI: see the badge in the README or https://github.com/Hugueninfer/ledgerlight/actions/workflows/ci.yml
