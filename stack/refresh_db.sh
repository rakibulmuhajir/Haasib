#!/bin/bash

# Kill connections to the test database
PGPASSWORD=AcctP@ss psql -h 127.0.0.1 -U superadmin -d postgres -c "SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = 'haasib_test3' AND pid <> pg_backend_pid();" 2>/dev/null

# Drop and recreate the database
PGPASSWORD=AcctP@ss dropdb -h 127.0.0.1 -U superadmin haasib_test3 2>/dev/null
PGPASSWORD=AcctP@ss createdb -h 127.0.0.1 -U superadmin haasib_test3

# Run migrations
php artisan migrate --force