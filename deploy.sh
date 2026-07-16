#!/usr/bin/env bash

set -Eeuo pipefail

# Manual production deployment for the Laravel application in ./build.
# Usage: ./deploy.sh
# Optional: DEPLOY_REMOTE=origin DEPLOY_BRANCH=main ./deploy.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${ROOT_DIR}/build"
REMOTE="${DEPLOY_REMOTE:-origin}"
BRANCH="${DEPLOY_BRANCH:-main}"
LOCK_FILE="${DEPLOY_LOCK_FILE:-/tmp/haasib-deploy.lock}"
APP_WAS_PUT_DOWN=0

log() {
    printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$1"
}

fail() {
    printf '\nDeployment stopped: %s\n' "$1" >&2
    exit 1
}

bring_application_up() {
    if [[ "${APP_WAS_PUT_DOWN}" -eq 1 ]]; then
        log "Bringing the application back online"
        (cd "${APP_DIR}" && php artisan up) || true
    fi
}

trap bring_application_up EXIT

for command in git php composer npm flock; do
    command -v "${command}" >/dev/null 2>&1 || fail "Required command not found: ${command}"
done

[[ -f "${APP_DIR}/artisan" ]] || fail "Laravel application not found at ${APP_DIR}"

exec 9>"${LOCK_FILE}"
flock -n 9 || fail "Another Haasib deployment is already running"

cd "${ROOT_DIR}"

CURRENT_BRANCH="$(git branch --show-current)"
[[ "${CURRENT_BRANCH}" == "${BRANCH}" ]] || fail "Expected branch ${BRANCH}, found ${CURRENT_BRANCH}"

# Vite output is generated during deployment. Restore any tracked generated files
# from the previous build before checking whether server-side source files changed.
git restore --worktree -- build/public/build 2>/dev/null || true

DIRTY_FILES="$(git status --porcelain=v1 --untracked-files=no)"
if [[ -n "${DIRTY_FILES}" ]]; then
    printf '\nDeployment stopped: the server has uncommitted tracked changes:\n%s\n' "${DIRTY_FILES}" >&2
    exit 1
fi

log "Fetching ${REMOTE}/${BRANCH}"
git fetch --prune "${REMOTE}" "${BRANCH}"

LOCAL_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "${REMOTE}/${BRANCH}")"

if [[ "${LOCAL_COMMIT}" == "${REMOTE_COMMIT}" ]]; then
    log "Code is already current; refreshing dependencies, migrations, and caches"
elif ! git merge-base --is-ancestor "${LOCAL_COMMIT}" "${REMOTE_COMMIT}"; then
    fail "Server branch has diverged from ${REMOTE}/${BRANCH}; resolve it manually"
fi

log "Putting the application into maintenance mode"
cd "${APP_DIR}"
php artisan down --retry=60
APP_WAS_PUT_DOWN=1

log "Updating code with a fast-forward-only merge"
cd "${ROOT_DIR}"
git merge --ff-only "${REMOTE}/${BRANCH}"

log "Installing production PHP dependencies"
cd "${APP_DIR}"
composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

log "Installing locked frontend dependencies"
npm ci --no-audit --no-fund

log "Building frontend assets"
npm run build

log "Clearing stale Laravel caches"
php artisan optimize:clear

log "Running database migrations"
php artisan migrate --force

log "Synchronizing permissions"
php artisan app:sync-permissions
php artisan app:sync-role-permissions

log "Building production caches"
php artisan optimize

log "Restarting queue workers"
php artisan queue:restart

log "Bringing the application online"
php artisan up
APP_WAS_PUT_DOWN=0
trap - EXIT

log "Deployment complete: $(git -C "${ROOT_DIR}" rev-parse --short HEAD)"
