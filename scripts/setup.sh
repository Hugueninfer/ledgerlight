#!/bin/sh
# First command after cloning: enables the repo's git hooks.
set -e
cd "$(dirname "$0")/.."
git config core.hooksPath .githooks
echo "Git hooks enabled (core.hooksPath=.githooks)."
