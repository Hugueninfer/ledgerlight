#!/usr/bin/env bash
# PreToolUse(Bash): block commands that bypass the repo's git safety rails.
# Reads the hook JSON on stdin; exit 2 blocks the call and feeds stderr back to the agent.
cmd="$(jq -r '.tool_input.command // empty' 2>/dev/null)"
[ -z "$cmd" ] && exit 0

block() { printf 'Blocked: %s\n' "$1" >&2; exit 2; }

ws='[[:space:]]'
is_git() { printf '%s' "$cmd" | grep -Eq "git$ws+$1($ws|\$)"; }

if is_git commit || is_git push; then
  printf '%s' "$cmd" | grep -Eq -- "(^|$ws)--no-verify($ws|\$)" \
    && block "--no-verify skips the repo git hooks. Fix the underlying problem instead."
fi

if is_git commit; then
  printf '%s' "$cmd" | grep -Eq -- "(^|$ws)-[a-zA-Z]*n[a-zA-Z]*($ws|\$)" \
    && block "git commit -n is --no-verify. Fix the underlying problem instead."
  printf '%s' "$cmd" | grep -Eiq 'co-authored-by:|generated with \[?claude' \
    && block "no Co-Authored-By or AI attribution trailers in commits (see CLAUDE.md)."
fi

if is_git push; then
  printf '%s' "$cmd" | grep -Eq -- "(^|$ws)(--force|-[a-zA-Z]*f[a-zA-Z]*)($ws|\$)" \
    && block "force push is not allowed. Use --force-with-lease on a feature branch if you must."
  printf '%s' "$cmd" | grep -Eq -- "$ws\+[A-Za-z0-9_./-]+" \
    && block "force push via +refspec is not allowed."
fi

exit 0
