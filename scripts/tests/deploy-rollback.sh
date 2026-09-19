#!/usr/bin/env bash
# Run the real deploy script against disposable files and fake external commands.
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SANDBOX="$(mktemp -d -t haasib-deploy-test.XXXXXXXX)"
[[ -d "$SANDBOX" && "$(basename "$SANDBOX")" == haasib-deploy-test.* ]] || exit 1
trap 'rm -rf -- "$SANDBOX"' EXIT
mkdir -p "$SANDBOX/bin"

cat > "$SANDBOX/bin/git" <<'SH'
#!/usr/bin/env bash
set -eu
[[ "${1:-}" != -C ]] || shift 2
case "$1" in
    branch) echo main ;;
    status|restore|fetch|merge-base) ;;
    rev-parse)
        if [[ "$2" == origin/main ]]; then echo new; else cat "$DEPLOY_ROOT_DIR/code"; fi ;;
    merge) echo new > "$DEPLOY_ROOT_DIR/code" ;;
    reset) echo old > "$DEPLOY_ROOT_DIR/code" ;;
    show) echo new ;;
    archive) tar -cf - -C "$DEPLOY_ROOT_DIR/published" . ;;
    *) echo "Unexpected git command: $*" >&2; exit 99 ;;
esac
SH
cat > "$SANDBOX/bin/php" <<'SH'
#!/usr/bin/env bash
set -eu
[[ "$PWD" == "$DEPLOY_ROOT_DIR/build" ]] || exit 98
echo "$*" >> "$DEPLOY_ROOT_DIR/php.log"
if [[ "${2:-}" == "$FAIL_STEP" ]]; then exit 42; fi
SH
printf '#!/usr/bin/env bash\nexit 0\n' > "$SANDBOX/bin/composer"
printf '#!/usr/bin/env bash\nexit 0\n' > "$SANDBOX/bin/flock"
chmod +x "$SANDBOX/bin/"*

for step in migrate app:sync-permissions optimize success; do
    case_dir="$SANDBOX/$step"
    mkdir -p "$case_dir/build/public/build" "$case_dir/published"
    touch "$case_dir/build/artisan"
    echo old > "$case_dir/code"
    echo old > "$case_dir/build/public/build/manifest.json"
    echo new > "$case_dir/published/manifest.json"
    result=0
    PATH="$SANDBOX/bin:$PATH" DEPLOY_REEXEC=1 DEPLOY_ROOT_DIR="$case_dir" \
        DEPLOY_LOCK_FILE="$case_dir/lock" FAIL_STEP="$step" \
        bash "$ROOT/deploy.sh" > "$case_dir/output" 2>&1 || result=$?
    expected=old
    if [[ "$step" == success ]]; then
        [[ "$result" == 0 ]] || { cat "$case_dir/output"; exit 1; }
        expected=new
    else
        [[ "$result" == 42 ]] || { cat "$case_dir/output"; exit 1; }
    fi
    [[ "$(cat "$case_dir/code")" == "$expected" ]]
    [[ "$(cat "$case_dir/build/public/build/manifest.json")" == "$expected" ]]
    grep -qx 'artisan up' "$case_dir/php.log"
    echo "PASS: $step leaves matching $expected code and assets, and brings the app up"
done
