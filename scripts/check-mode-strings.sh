#!/usr/bin/env bash
set -euo pipefail

# Find mode-specific strings hardcoded via ternaries.
# Enforce: use useLexicon() instead of isAccountantMode ? '...' : '...'

ROOTS=("build/resources/js" "build/modules")
PATTERNS=(
  "isAccountantMode[^\\n]*\\?.*'[^']*'"
  "isAccountantMode[^\\n]*\\?.*\\\"[^\\\"]*\\\""
)

SEARCH_BIN=""
if command -v rg >/dev/null 2>&1; then
  SEARCH_BIN="rg --no-heading -n"
elif command -v grep >/dev/null 2>&1; then
  SEARCH_BIN="grep -R -n -E"
else
  echo "No search tool (rg/grep) available" >&2
  exit 1
fi

found=0
for dir in "${ROOTS[@]}"; do
  for pat in "${PATTERNS[@]}"; do
    if $SEARCH_BIN "$pat" "$dir" | grep -v "modeKey" | grep -v "modeLabel" ; then
      echo "Mode-specific string detected; use useLexicon(): pattern '$pat' in $dir"
      found=1
    fi
  done
done

exit $found
