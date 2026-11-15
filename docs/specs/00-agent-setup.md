# Stage 0: Repo and agent setup

## Goal
A repository where an AI agent can work safely and visibly: conventions written down, mistakes blocked by hooks rather than by hope.

## Scope
- Repo basics: README skeleton, `.editorconfig`, `.gitignore`, Conventional Commit lint (`.githooks/commit-msg`, enabled by `scripts/setup.sh`).
- `CLAUDE.md` (map, commands, global constraints, how we work), `AGENTS.md`, path-scoped rules in `.claude/rules/`.
- Claude Code hooks wired in `.claude/settings.json`: `guard-bash.sh`, `guard-files.sh`, `format-file.sh`.
- `docs/ai-workflow.md` with a corrections log.

## Acceptance checks
- `bash tests/hooks/commit-msg.test.sh` and `bash tests/hooks/claude-hooks.test.sh` pass.
- `git commit --no-verify`, `git push --force` and AI co-author trailers are blocked (exit 2 with a reason).
- `.env` and `.env.production` edits are blocked; `.env.example` is allowed.
- A migration already on `origin/main` cannot be edited; new migrations can. (The test prints SKIP while no migration exists on `origin/main`; since stage 1 one does, so it runs.)
- `format-file.sh` never blocks, even with Docker down.

## Outcome

**What.** Conventions and guards for agent work: `CLAUDE.md` and `AGENTS.md`, three path-scoped rule files, three Claude Code hooks wired in `.claude/settings.json`, a Conventional Commit git hook, and shell tests for all of them (`tests/hooks/`, 48 checks as of stage 2: 19 for the commit-msg hook, 29 for the Claude Code hooks).

**Why.** Written rules get ignored under pressure; hooks do not. The riskiest agent habits here (skipping hooks, force pushing, adding AI trailers, touching secrets, rewriting shipped migrations) are blocked deterministically, and the rest is steered by rules that load only when relevant.

**How I steered the agent.** Spec first, then a failing hook test (RED: all 23 checks failed because the scripts did not exist), then the scripts (GREEN). I overrode the brief where reality differed: commits go straight to `main`, the migration rule was implemented before any migration existed on `origin/main`, so its test printed SKIP until stage 1 merged one (it was verified once by hand in a throwaway clone), and the file guard allows paths outside the repo and tolerates missing input.

**How to verify.**
```bash
bash scripts/setup.sh
bash tests/hooks/commit-msg.test.sh     # 19 passed
bash tests/hooks/claude-hooks.test.sh   # 29 passed, 0 SKIP (the migration check runs once a migration is on origin/main)
jq . .claude/settings.json
```
