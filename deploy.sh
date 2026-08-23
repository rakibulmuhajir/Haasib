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

# Vite empties its output directory before it writes anything, so a build that
# dies partway -- out of memory, syntax error, full disk -- leaves the live
# release with no assets and no manifest, and every page answers 500. That is
# not hypothetical: it took the site down for four minutes on 22 August 2026.
# So the build writes to a directory nothing is serving, and only a build that
# finished is moved into place. A snapshot taken before anything is touched
# covers the rest of the window, including the merge itself.
BUILD_DIR="${APP_DIR}/public/build"
STAGING_DIR="${APP_DIR}/public/build-staging"
PREVIOUS_DIR="${APP_DIR}/public/build-previous"
ASSETS_NEED_RESTORING=0

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

# Runs before the application is brought back up, so whatever is served is
# either the new release or the one that was working before this ran.
restore_previous_assets() {
    rm -rf "${STAGING_DIR}"

    if [[ "${ASSETS_NEED_RESTORING}" -eq 1 && -d "${PREVIOUS_DIR}" ]]; then
        log "Deployment did not complete; restoring the previous frontend assets"
        rm -rf "${BUILD_DIR}"
        mv "${PREVIOUS_DIR}" "${BUILD_DIR}"
    fi
}

on_exit() {
    restore_previous_assets
    bring_application_up
}

trap on_exit EXIT

for command in git php composer npm flock; do
    command -v "${command}" >/dev/null 2>&1 || fail "Required command not found: ${command}"
done

[[ -f "${APP_DIR}/artisan" ]] || fail "Laravel application not found at ${APP_DIR}"

exec 9>"${LOCK_FILE}"
flock -n 9 || fail "Another Haasib deployment is already running"

cd "${ROOT_DIR}"

CURRENT_BRANCH="$(git branch --show-current)"
[[ "${CURRENT_BRANCH}" == "${BRANCH}" ]] || fail "Expected branch ${BRANCH}, found ${CURRENT_BRANCH}"

# Taken before anything else touches the worktree, so it holds the assets the
# site is actually serving right now rather than whatever git has an opinion
# about. Everything from here to the swap is covered by it.
if [[ -d "${BUILD_DIR}" ]]; then
    log "Snapshotting the frontend assets currently being served"
    rm -rf "${PREVIOUS_DIR}"
    cp -a "${BUILD_DIR}" "${PREVIOUS_DIR}"
    ASSETS_NEED_RESTORING=1
fi

# build/public/build is ignored, yet a handful of files inside it were committed
# anyway, so the worktree reads as dirty and the merge below refuses to run.
# Discarding them is safe because the snapshot above already holds the real
# ones. The commit this deploys untracks them, after which this is a no-op.
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
rm -rf "${STAGING_DIR}"
npm run build -- --outDir public/build-staging --emptyOutDir

[[ -f "${STAGING_DIR}/manifest.json" ]] || fail "The build produced no manifest; the previous assets have been kept"

log "Swapping the new frontend assets into place"
rm -rf "${BUILD_DIR}"
mv "${STAGING_DIR}" "${BUILD_DIR}"

# Past this point the assets are complete and belong to the code that was just
# merged. A later step failing is a reason to look at that step, not to put the
# previous release's assets back under the new code and guarantee a manifest
# that names files nothing built. The snapshot stays on disk until the end so a
# rollback by hand is still one mv away.
ASSETS_NEED_RESTORING=0

log "Clearing stale Laravel caches"
php artisan optimize:clear

log "Running database migrations"
php artisan migrate --force

log "Synchronizing permissions"
php artisan app:sync-permissions
php artisan app:sync-role-permissions
php artisan app:sync-company-user-roles

log "Building production caches"
php artisan optimize

log "Restarting queue workers"
php artisan queue:restart

log "Bringing the application online"
php artisan up
APP_WAS_PUT_DOWN=0
ASSETS_NEED_RESTORING=0
rm -rf "${PREVIOUS_DIR}"
trap - EXIT

log "Deployment complete: $(git -C "${ROOT_DIR}" rev-parse --short HEAD)"
