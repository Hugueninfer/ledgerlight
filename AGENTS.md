# AGENTS.md

Guidance for AI coding agents working in this repository.

- Start with [`CLAUDE.md`](CLAUDE.md): project map, commands, global constraints and how we work. It applies to every agent, not only Claude.
- Path-scoped rules live in [`.claude/rules/`](.claude/rules/): `backend.md` (tenancy, error contract, Pest), `frontend.md` (design tokens, `components/ui/`), `testing.md` (external services always faked).
- Write a mini-spec in `docs/specs/` before code, work test-first, and commit small and Conventional, with no AI attribution trailers.
- Do not bypass guards: no `--no-verify`, no force pushes, no edits to `.env*` or to migrations already on `origin/main`.
