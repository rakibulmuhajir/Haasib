#!/bin/bash

# Script to refresh migrations with multi-schema database support
# Usage: ./refresh-migrations.sh [database_name] [--seed]

set -e  # Exit on any error

# Default database name if not provided
DATABASE=${1:-haasib_test3}
SEED_FLAG=""

# Check if --seed flag is provided
if [[ "$2" == "--seed" ]] || [[ "$1" == "--seed" ]]; then
    SEED_FLAG="--seed"
    # Adjust database name if --seed was the first argument
    if [[ "$1" == "--seed" ]]; then
        DATABASE=${2:-haasib_test3}
    fi
fi

echo "=================================================="
echo "Refreshing Multi-Schema Database Migrations"
echo "Database: $DATABASE"
echo "Seed: $([[ -n "$SEED_FLAG" ]] && echo "Yes" || echo "No")"
echo "=================================================="

# Set password for PostgreSQL
export PGPASSWORD='AcctP@ss'

# Check if database exists
echo "Checking database connection..."
psql -h 127.0.0.1 -U superadmin -d $DATABASE -c "SELECT 1;" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Error: Cannot connect to database '$DATABASE'"
    echo "Please ensure:"
    echo "1. PostgreSQL is running"
    echo "2. Database '$DATABASE' exists"
    echo "3. User 'superadmin' has access"
    exit 1
fi

echo "✓ Database connection verified"

# Drop all schemas
echo ""
echo "Dropping all schemas and their tables..."
psql -h 127.0.0.1 -U superadmin -d $DATABASE -c "
    DROP SCHEMA IF EXISTS auth CASCADE;
    DROP SCHEMA IF EXISTS hrm CASCADE;
    DROP SCHEMA IF EXISTS acct CASCADE;
    DROP SCHEMA public CASCADE;
    CREATE SCHEMA public;
" > /dev/null 2>&1

echo "✓ All schemas dropped successfully"

# Run Laravel migrations
echo ""
echo "Running Laravel migrations..."
cd app && php artisan migrate --database=$DATABASE

# Run seeders if requested
if [[ -n "$SEED_FLAG" ]]; then
    echo ""
    echo "Running database seeders..."
    php artisan db:seed --database=$DATABASE
fi

echo ""
echo "=================================================="
echo "Migration refresh completed successfully!"
echo "=================================================="

# Show table counts
echo ""
echo "Verifying schema creation..."
psql -h 127.0.0.1 -U superadmin -d $DATABASE -c "
    SELECT
        schemaname,
        COUNT(*) as table_count
    FROM pg_tables
    WHERE schemaname IN ('auth', 'public', 'hrm', 'acct')
    GROUP BY schemaname
    ORDER BY schemaname;
"

echo ""
echo "Done!"