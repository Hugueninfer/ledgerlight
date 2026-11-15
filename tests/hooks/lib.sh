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
