#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
# shellcheck source=lib.sh
. "$ROOT/tests/hooks/lib.sh"

MIG="api/database/migrations"

# guard-bash: blocks (exit 2)
expect_block bash '{"tool_input":{"command":"git commit --no-verify -m \"feat: x\""}}'
expect_block bash '{"tool_input":{"command":"git commit -n -m \"feat: x\""}}'
expect_block bash '{"tool_input":{"command":"git commit -m \"feat: x\" -m \"Co-Authored-By: Claude <noreply@anthropic.com>\""}}'
expect_block bash '{"tool_input":{"command":"git push --force origin main"}}'
expect_block bash '{"tool_input":{"command":"git push -f origin main"}}'
# guard-bash: allows
expect_allow bash '{"tool_input":{"command":"git commit -m \"feat(api): add health\""}}'
expect_allow bash '{"tool_input":{"command":"git push --force-with-lease origin stage/01-infra"}}'
expect_allow bash '{"tool_input":{"command":"git push origin main --follow-tags"}}'
expect_allow bash '{"tool_input":{"command":"ls -la"}}'
expect_allow bash '{}'
expect_allow bash ''

# guard-files: blocks
expect_block files '{"tool_input":{"file_path":"'"$ROOT"'/api/.env"}}'
expect_block files '{"tool_input":{"file_path":"'"$ROOT"'/.env.production"}}'
# guard-files: allows
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/api/.env.example"}}'
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/'"$MIG"'/2026_11_20_000000_new_migration.php"}}'
expect_allow files '{"tool_input":{"file_path":"'"$ROOT"'/README.md"}}'
expect_allow files '{"tool_input":{"file_path":"/tmp/outside-repo/.env.local"}}'
expect_allow files '{"tool_input":{}}'
expect_allow files '{"tool_input":{"file_path":""}}'
expect_allow files ''

# guard-files: a migration already on origin/main is immutable (skipped until one exists)
merged="$(git -C "$ROOT" ls-tree -r --name-only origin/main -- "$MIG" 2>/dev/null | head -n 1)"
if [ -n "$merged" ]; then
  expect_block files '{"tool_input":{"file_path":"'"$ROOT/$merged"'"}}'
else
  skip "blocks editing a migration already on origin/main (no migration on origin/main yet)"
fi

# format-file: never blocks, even when containers are down
expect_allow format '{"tool_input":{"file_path":"'"$ROOT"'/api/app/Models/User.php"}}'
expect_allow format '{"tool_input":{"file_path":"'"$ROOT"'/web/app/app.vue"}}'
expect_allow format '{}'

finish
