#!/usr/bin/env bash
# PostToolUse(Edit|Write|MultiEdit): best-effort formatting. Never blocks: always exits 0.
path="$(jq -r '.tool_input.file_path // empty' 2>/dev/null)"
[ -z "$path" ] && exit 0

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
case "$path" in
  "$root"/*) rel="${path#"$root"/}" ;;
  /*) exit 0 ;;
  *) rel="${path#./}" ;;
esac
[ -f "$root/$rel" ] || exit 0

case "$rel" in
  api/*.php)
    if (cd "$root" && docker compose ps --status running api 2>/dev/null | grep -q 'api'); then
      (cd "$root" && docker compose exec -T api vendor/bin/pint "${rel#api/}") >&2 || true
    fi
    ;;
  web/*.ts|web/*.vue|web/*.js)
    if [ -d "$root/web/node_modules" ]; then
      (cd "$root" && pnpm --dir web exec eslint --fix "${rel#web/}") >&2 || true
    fi
    ;;
esac
exit 0
