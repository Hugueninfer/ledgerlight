# Ledgerlight

Multi-tenant accounts-payable SaaS: an LLM extracts invoice fields with a confidence score, humans review the uncertain ones, approved invoices are exported to an unreliable accounting API (Tallyline). Design: `docs/superpowers/specs/2026-11-15-ledgerlight-design.md`. Roadmap: `docs/superpowers/plans/2026-11-15-roadmap.md`.

## Project map

| Path | What |
|---|---|
| `api/` | Laravel API (Pest, Pint, Larastan), added in stage 1 |
| `web/` | Nuxt app (Vitest, ESLint), added in stage 1 |
| `docs/specs/NN-*.md` | One mini-spec per stage, written before code |
| `docs/superpowers/` | Design spec, roadmap and per-stage plans |
| `docs/ai-workflow.md` | How the agent is steered, plus the corrections log |
| `.claude/rules/` | Path-scoped rules, loaded when matching files are touched |
| `.claude/hooks/` | Guard hooks (git safety, secrets, merged migrations) and the format hook |
| `.githooks/` | `commit-msg`: Conventional Commits, no AI trailers (enabled by `scripts/setup.sh`) |
| `tests/hooks/` | Shell tests for the hooks: `bash tests/hooks/*.test.sh` |

## Commands

```bash
bash scripts/setup.sh                           # once after cloning: enable git hooks
bash tests/hooks/commit-msg.test.sh             # commit-msg hook tests
bash tests/hooks/claude-hooks.test.sh           # Claude Code hook tests
docker compose exec api vendor/bin/pest         # backend tests (from stage 1)
docker compose exec api vendor/bin/pint         # backend formatting
pnpm --dir web run test                         # frontend tests (from stage 1)
make lint && make test                          # everything: pint, larastan, eslint, nuxi typecheck, Pest, Vitest
# note: the web targets of make lint/test run on the host and need Node 22 + pnpm
```

There is no PHP or Composer on the host. Run every PHP command in the `api` container or a throwaway `composer:2` container.

## Global constraints

- Everything in English: code, comments, docs, commit messages, UI copy.
- Conventional Commits (`type(scope): subject`), many small commits, **no `Co-Authored-By` or other AI attribution trailers**.
- All commits go **directly to `main`** (no PRs). Each stage closes with an "Outcome" section appended to its mini-spec in `docs/specs/` (What / Why / How I steered the agent / How to verify) and an annotated tag `stage-NN` (`git tag -a stage-01 -m "Stage 1: infra"`), pushed with `git push origin main --follow-tags`.
- No PHP or Composer on the host: PHP commands run in the `api` container (`docker compose exec api ...`) or a throwaway `composer:2` container.
- Image targets must build for `linux/arm64` (production) and `linux/amd64` (dev, CI).
- Money in integer cents. Tenant-owned tables always carry `tenant_id`.
- API errors always use the JSON contract `{ "message": string, "code": string, "request_id": string }` (plus `errors` on 422), regardless of the request's `Accept` header.
- Request ids look like `req_` + 8 alphanumeric chars (e.g. `req_4Fh9Kp21`) and are echoed in the `X-Request-Id` header.
- Dev URL: `http://localhost:8088`. Total hosting cost: $0.

## How we work

1. **Spec first.** Write `docs/specs/NN-<stage>.md` (10-20 lines: goal, scope, acceptance checks) before any code.
2. **TDD.** A failing test comes first; watch it fail for the right reason, then make it pass.
3. **Small commits.** One logical change per commit, Conventional Commits, English.
4. **Verify before claiming done.** Run the tests and read the output.
5. **Record corrections.** When the owner overrides what the agent did, add a row to the corrections log in `docs/ai-workflow.md`.

Hooks enforce part of this: `--no-verify`, force pushes and AI trailers are blocked, `.env*` files (except `.env.example`) and migrations already on `origin/main` are read-only, and edited files are auto-formatted once `api/` and `web/` exist (stage 1). If a hook blocks you, fix the cause; do not look for a way around it.
