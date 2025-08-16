#!/usr/bin/env bash
# deploy.sh — Apply modular SME schema to a PostgreSQL database in correct order.
# Usage: PGDATABASE=db PGUSER=user PGPASSWORD=pass PGHOST=host PGPORT=5432 ./deploy.sh
# Optional: SCHEMA_DIR override (default: current dir)

set -euo pipefail

SCHEMA_DIR=${SCHEMA_DIR:-$(pwd)}
PSQL_ARGS=("--no-psqlrc" "--set" "ON_ERROR_STOP=1")

# Load order
FILES=(
  "00_core.sql"
  "10_accounting.sql"
  "11_ar.sql"
  "12_ap.sql"
  "13_bank.sql"
  "20_inventory.sql"
  "30_reporting.sql"
  "40_crm.sql"
  "50_payroll.sql"
  "60_vms.sql"
  "90_system.sql"
)

missing=()
for f in "${FILES[@]}"; do
  [[ -f "${SCHEMA_DIR}/${f}" ]] || missing+=("${f}")
done

if (( ${#missing[@]} > 0 )); then
  echo "Missing files:" "${missing[@]}" >&2
  exit 1
fi

# Apply each file in its own transaction (BEGIN/COMMIT already present, but enforced here)
for f in "${FILES[@]}"; do
  echo "==> Applying ${f}"
  # Wrap with a transaction guard in case file lacks BEGIN/COMMIT in future
  ( echo "BEGIN;"; cat "${SCHEMA_DIR}/${f}"; echo "COMMIT;" ) | psql "${PSQL_ARGS[@]}"
  echo "==> Done ${f}"
  echo
done

echo "All modules applied successfully."
