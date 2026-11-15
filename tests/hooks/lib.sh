#!/usr/bin/env bash
# Shared helpers for hook tests. Source this file; set HOOK before calling expect_*.
PASSED=0
FAILED=0
_TMP="$(mktemp -d)"
trap 'rm -rf "$_TMP"' EXIT

pass() { PASSED=$((PASSED + 1)); printf 'PASS: %s\n' "$1"; }
fail() { FAILED=$((FAILED + 1)); printf 'FAIL: %s\n' "$1"; }

_run_hook() {
  printf '%s\n' "$1" > "$_TMP/msg"
  sh "$HOOK" "$_TMP/msg" > /dev/null 2>&1
}

expect_ok() {
  if _run_hook "$1"; then pass "accepts: ${1%%$'\n'*}"; else fail "should accept: ${1%%$'\n'*}"; fi
}

expect_fail() {
  if _run_hook "$1"; then fail "should reject: ${1//$'\n'/ | }"; else pass "rejects: ${1//$'\n'/ | }"; fi
}

finish() {
  printf '\n%d passed, %d failed\n' "$PASSED" "$FAILED"
  [ "$FAILED" -eq 0 ]
}

skip() { printf 'SKIP: %s\n' "$1"; }

# Claude Code hook helpers: feed JSON on stdin to .claude/hooks/guard-<name>.sh
# (or format-file.sh for "format"); a block is exit code 2.
_hook_script() {
  case "$1" in
    bash) printf '%s' "$ROOT/.claude/hooks/guard-bash.sh" ;;
    files) printf '%s' "$ROOT/.claude/hooks/guard-files.sh" ;;
    format) printf '%s' "$ROOT/.claude/hooks/format-file.sh" ;;
  esac
}

_run_json_hook() {
  printf '%s' "$2" | bash "$(_hook_script "$1")" > /dev/null 2> "$_TMP/stderr"
}

expect_block() {
  _run_json_hook "$1" "$2"
  local code=$?
  if [ "$code" -eq 2 ] && [ -s "$_TMP/stderr" ]; then pass "$1 blocks: $2"; else fail "$1 should block (exit 2 + reason), got $code: $2"; fi
}

expect_allow() {
  _run_json_hook "$1" "$2"
  local code=$?
  if [ "$code" -eq 0 ]; then pass "$1 allows: $2"; else fail "$1 should allow, got $code: $2"; fi
}
