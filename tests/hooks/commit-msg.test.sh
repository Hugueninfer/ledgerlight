#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
HOOK="$ROOT/.githooks/commit-msg"
# shellcheck source=lib.sh
. "$ROOT/tests/hooks/lib.sh"

expect_ok   "feat(api): add health endpoint"
expect_ok   "docs: add product design spec and UI reference"
expect_ok   $'fix(web): handle empty inbox\n\nLonger body explaining why.'
expect_ok   "Merge branch 'x' into main"
expect_ok   "revert: undo bad change"
expect_fail "Added stuff"
expect_fail "feat: Add thing."
expect_fail "feat: add thing."
expect_fail "feat(API): add thing"
expect_fail "wip: add thing"
expect_fail "feat: add a very long subject line that goes well past the seventy-two character limit"
expect_fail $'feat(api): add x\n\nCo-Authored-By: Claude <noreply@anthropic.com>'
expect_fail $'feat(api): add x\n\nco-authored-by: Someone <a@b.c>'
expect_fail $'feat(api): add x\n\nGenerated with Claude Code'

finish
