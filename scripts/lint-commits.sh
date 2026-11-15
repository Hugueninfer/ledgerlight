#!/usr/bin/env bash
# Runs every commit message in a range through .githooks/commit-msg (the server-side twin of the
# local hook: `git revert`, `--no-verify` and web edits bypass local hooks, CI does not).
# Usage: scripts/lint-commits.sh <base> <head>
# An all-zero or unknown <base> (new branch, force push over rewritten history) lints only <head>.
set -euo pipefail

base="${1:?usage: lint-commits.sh <base> <head>}"
head="${2:?usage: lint-commits.sh <base> <head>}"
cd "$(dirname "$0")/.."

if [[ "$base" =~ ^0+$ ]] || ! git cat-file -e "${base}^{commit}" 2>/dev/null; then
  echo "No usable base ($base): linting only $head"
  range=(-1 "$head")
else
  range=("$base..$head")
fi

status=0
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
for sha in $(git rev-list --reverse "${range[@]}"); do
  git log -1 --format=%B "$sha" > "$tmp"
  if out="$(sh .githooks/commit-msg "$tmp" 2>&1)"; then
    echo "ok   ${sha:0:7} $(git log -1 --format=%s "$sha")"
  else
    echo "FAIL ${sha:0:7} $(git log -1 --format=%s "$sha"): $out" >&2
    status=1
  fi
done
exit "$status"
