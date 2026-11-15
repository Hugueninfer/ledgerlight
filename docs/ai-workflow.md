# AI workflow

How Ledgerlight is built with a coding agent, and how the agent is kept honest. The goal is that every claim made by the agent can be verified by a test, a hook or a diff.

## The loop

1. **Spec.** Each stage starts with a mini-spec in `docs/specs/NN-*.md`: goal, scope, acceptance checks.
2. **Rules.** `CLAUDE.md` holds the constraints that always apply; `.claude/rules/*.md` add path-scoped rules (backend, frontend, testing) that load only when relevant files are touched.
3. **Test first.** The agent writes a failing test, shows it failing, then implements.
4. **Hooks.** Deterministic guards run outside the model: they block `--no-verify`, force pushes, AI trailers, edits to `.env*` and to migrations already on `origin/main`, and format edited files (once `api/` and `web/` exist).
5. **Task by task.** Each plan task is implemented by an agent working from a written brief. A separate reviewer agent then checks the result for spec compliance and quality, and the fixes loop until the review is clean. Decisions the controller makes along the way (for example, scoping a finding to a later stage) are recorded in a ledger so they are not relitigated.
6. **Whole-plan review.** When all tasks of a plan are done, the most capable model reviews the whole diff. Its findings are fixed in one wave before the stage is closed.
7. **Owner decisions.** The owner decides in chat anything that changes direction (for example, committing straight to `main` instead of opening PRs). Stages close with an Outcome section in the spec and a `stage-NN` tag.

## Guards at a glance

| Layer | Mechanism | Enforces |
|---|---|---|
| Git | `.githooks/commit-msg` | Conventional Commits, no AI co-author trailers |
| Claude Code | `.claude/hooks/guard-bash.sh` | no `--no-verify`, no force push, no AI trailers |
| Claude Code | `.claude/hooks/guard-files.sh` | no `.env*` edits, merged migrations immutable |
| Claude Code | `.claude/hooks/format-file.sh` | Pint / ESLint after edits (best effort) |

The two guard hooks fail closed (exit 2) when `jq` is missing, rather than silently allowing everything. All hooks are covered by shell tests in `tests/hooks/`. The formatter branches are tested with `docker`/`pnpm` shims, not against real Pint or ESLint, which arrive in stage 1.

## Corrections log

Where the owner or a reviewer corrected what the agent did, the per-stage record is the "How I steered the agent" list in the Outcome section of each stage spec (`docs/specs/01-infra.md`, `docs/specs/02-tenancy.md`). This table holds only the corrections that changed a rule or a hook. The pattern of corrections is what drives new rules.

| Date | Stage | What the agent did | What I changed | Why |
|---|---|---|---|---|
| 2026-11-15 | CI (Task 6) | Reverted a diagnostic commit with `git revert`, producing header `Revert "ci: surface pest output as annotation for diagnosis"` (0843bca), which the commit-msg hook rejects but git's revert flow bypassed | Accepted 0843bca as a one-off (history not rewritten, no force-push); extended `.githooks/commit-msg` to accept git's `Revert "..."` header while still rejecting AI trailers | Pushed history is immutable here; the hook should match git's default revert format |
