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
expect_block bash '{"tool_input":{"command":"git -C api commit --no-verify -m \"feat: x\""}}'
expect_block bash '{"tool_input":{"command":"git -c user.name=x push --force origin main"}}'
expect_block bash '{"tool_input":{"command":"git --no-pager push -f origin main"}}'
# guard-bash: allows
expect_allow bash '{"tool_input":{"command":"git commit -m \"feat(api): add health\""}}'
expect_allow bash '{"tool_input":{"command":"git push --force-with-lease origin stage/01-infra"}}'
expect_allow bash '{"tool_input":{"command":"git push origin main --follow-tags"}}'
expect_allow bash '{"tool_input":{"command":"ls -la"}}'
expect_allow bash '{}'
expect_allow bash ''

# both guards fail closed (exit 2 + reason) when jq is missing, instead of silently allowing
J="$_TMP/nojq"
mkdir -p "$J"
for t in bash dirname basename cat grep sed git; do ln -sf "$(command -v "$t")" "$J/$t"; done
for g in bash files; do
  printf '{"tool_input":{"command":"ls","file_path":"/tmp/x"}}' | PATH="$J" "$(command -v bash)" "$(_hook_script "$g")" > /dev/null 2> "$_TMP/stderr"
  code=$?
  if [ "$code" -eq 2 ] && grep -q 'jq is required for the guard hooks' "$_TMP/stderr"; then
    pass "guard-$g fails closed without jq"
  else
    fail "guard-$g should exit 2 naming jq when jq is missing, got $code"
  fi
done

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

# format-file: never blocks, and really attempts to format. Run in a temp root with
# docker/pnpm shims: docker reports "api" running but fails on exec, pnpm fails.
F="$_TMP/fmt"
mkdir -p "$F/api/app/Models" "$F/web/app" "$F/web/node_modules" "$_TMP/bin"
touch "$F/api/app/Models/User.php" "$F/web/app/app.vue"
for tool in docker pnpm; do
  cat > "$_TMP/bin/$tool" <<SHIM
#!/bin/sh
echo "$tool \$*" >> "$_TMP/calls"
[ "$tool" = docker ] && [ "\$2" = ps ] && { echo api; exit 0; }
exit 1
SHIM
  chmod +x "$_TMP/bin/$tool"
done
fmt() { : > "$_TMP/calls"; printf '{"tool_input":{"file_path":"%s"}}' "$1" | PATH="$_TMP/bin:$PATH" LEDGERLIGHT_ROOT="$F" bash "$ROOT/.claude/hooks/format-file.sh" > /dev/null 2>&1; }
fmt "$F/api/app/Models/User.php"; code=$?
if [ "$code" -eq 0 ] && grep -q 'docker compose exec -T api vendor/bin/pint app/Models/User.php' "$_TMP/calls"; then
  pass "format runs pint for api php and exits 0 when the formatter fails"; else fail "format php (exit $code): $(cat "$_TMP/calls")"; fi
fmt "$F/web/app/app.vue"; code=$?
if [ "$code" -eq 0 ] && grep -q 'pnpm --dir web exec eslint --fix app/app.vue' "$_TMP/calls"; then
  pass "format runs eslint for web vue and exits 0 when the formatter fails"; else fail "format vue (exit $code): $(cat "$_TMP/calls")"; fi
expect_allow format '{}'

finish
