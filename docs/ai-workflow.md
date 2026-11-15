# AI workflow

How Ledgerlight is built with a coding agent, and how the agent is kept honest. The goal is that every claim made by the agent can be verified by a test, a hook or a diff.

## The loop

1. **Spec.** Each stage starts with a mini-spec in `docs/specs/NN-*.md`: goal, scope, acceptance checks.
2. **Rules.** `CLAUDE.md` holds the constraints that always apply; `.claude/rules/*.md` add path-scoped rules (backend, frontend, testing) that load only when relevant files are touched.
3. **Test first.** The agent writes a failing test, shows it failing, then implements.
4. **Hooks.** Deterministic guards run outside the model: they block `--no-verify`, force pushes, AI trailers, edits to `.env*` and to migrations already on `origin/main`, and format edited files (once `api/` and `web/` exist).
5. **Review.** Every diff is read by the owner before a stage is tagged. Stages close with an Outcome section in the spec and a `stage-NN` tag.

## Guards at a glance

| Layer | Mechanism | Enforces |
|---|---|---|
| Git | `.githooks/commit-msg` | Conventional Commits, no AI co-author trailers |
| Claude Code | `.claude/hooks/guard-bash.sh` | no `--no-verify`, no force push, no AI trailers |
| Claude Code | `.claude/hooks/guard-files.sh` | no `.env*` edits, merged migrations immutable |
| Claude Code | `.claude/hooks/format-file.sh` | Pint / ESLint after edits (best effort) |

All hooks are covered by shell tests in `tests/hooks/`. The formatter branches are tested with `docker`/`pnpm` shims, not against real Pint or ESLint, which arrive in stage 1.

## Corrections log

Every time the owner overrides or fixes what the agent did, add a row. The pattern of corrections is what drives new rules.

| Date | Stage | What the agent did | What I changed | Why |
|---|---|---|---|---|
| | | | | |
