#!/usr/bin/env bash
# PreToolUse(Edit|Write|MultiEdit): protect secrets and merged migrations.
# Reads the hook JSON on stdin; exit 2 blocks the call and feeds stderr back to the agent.
path="$(jq -r '.tool_input.file_path // empty' 2>/dev/null)"
[ -z "$path" ] && exit 0

block() { printf 'Blocked: %s\n' "$1" >&2; exit 2; }

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "$path" in
  "$root"/*) rel="${path#"$root"/}" ;;
  /*) exit 0 ;;          # outside the repository
  *) rel="${path#./}" ;;
esac

base="$(basename "$rel")"
case "$base" in
  .env.example) ;;
  .env|.env.*) block "$rel is an environment file and may hold secrets. Edit .env.example and tell the owner what to set." ;;
esac

case "$rel" in
  api/database/migrations/*)
    if git -C "$root" cat-file -e "origin/main:$rel" 2>/dev/null; then
      block "Migration already merged to main; create a new migration instead."
    fi
    ;;
esac

exit 0
