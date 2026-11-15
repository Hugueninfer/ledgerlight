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
- A migration already on `origin/main` cannot be edited; new migrations can. (Test prints SKIP until the first migration is merged in stage 2.)
- `format-file.sh` never blocks, even with Docker down.
