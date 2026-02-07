# CLI & Deployment Guide

**Last Updated**: 2025-02-01  
**Purpose**: CLI commands, artisan commands, deployment procedures  
**Audience**: DevOps and developers

---

## Table of Contents

1. [Development Server](#1-development-server)
2. [Artisan Commands](#2-artisan-commands)
3. [Database Operations](#3-database-operations)
4. [RBAC Commands](#4-rbac-commands)
5. [Testing Commands](#5-testing-commands)
6. [Production Deployment](#6-production-deployment)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Development Server

### 1.1 Start Development Environment

```bash
# Terminal 1: Start Laravel Octane (FrankenPHP)
php artisan octane:start --server=frankenphp --port=9001 --watch

# Terminal 2: Start Vite dev server
npm run dev

# Access application
open http://localhost:5180
```

### 1.2 Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

---

## 2. Artisan Commands

### 2.1 Code Generation

```bash
# Create controller
php artisan make:controller InvoiceController --resource

# Create model with factory
php artisan make:model Invoice -f

# Create migration
php artisan make:migration create_invoices_table

# Create FormRequest
php artisan make:request StoreInvoiceRequest

# Create middleware
php artisan make:middleware EnsureCompanyContext

# Create action
php artisan make:action ProcessInvoice

# Create test
php artisan make:test InvoiceTest
```

### 2.2 Application Maintenance

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue worker
php artisan queue:work
php artisan queue:work --sleep=3 --tries=3

# Schedule run
php artisan schedule:run
```

---

## 3. Database Operations

### 3.1 Migration Commands

```bash
# Run all pending migrations
php artisan migrate

# Run specific migration
php artisan migrate --path=database/migrations/2025_01_15_000001_create_invoices_table.php

# Rollback last batch
php artisan migrate:rollback

# Rollback specific steps
php artisan migrate:rollback --step=3

# Fresh database (drop all tables)
php artisan migrate:fresh

# Fresh with seeders
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status

# Single migration (for testing)
php artisan migrate --path=database/migrations/2025_01_15_000001_create_invoices_table.php
```

### 3.2 Database Seeding

```bash
# Seed with default seeders
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=UserSeeder

# Fresh and seed
php artisan migrate:fresh --seed

# Seed in specific order
php artisan db:seed --class=CurrencySeeder
php artisan db:seed --class=CompanySeeder
php artisan db:seed --class=RoleSeeder
```

---

## 4. RBAC Commands

### 4.1 Permission Management

```bash
# Sync all permissions from config
php artisan rbac:sync-permissions

# Sync permissions with verbose output
php artisan rbac:sync-permissions -v
```

### 4.2 Role Management

```bash
# Sync role permissions for all companies
php artisan rbac:sync-role-permissions

# Sync for specific company
php artisan rbac:sync-role-permissions --company=abc-123

# Reset permission cache
php artisan permission:cache-reset

# List all permissions
php artisan tinker --execute="echo Permission::all()->pluck('name')"
```

### 4.3 User Management

```bash
# Create super admin user
php artisan tinker
>>> $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
>>> $user->assignRole('super_admin');

# Assign role to user
>>> $user->assignRole('owner');

# Remove role
>>> $user->removeRole('owner');

# Check permissions
>>> $user->can('invoices.create');
```

---

## 5. Testing Commands

### 5.1 PHPUnit/Pest

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/InvoiceTest.php

# Run specific test method
php artisan test --filter=test_can_create_invoice

# Run in parallel
php artisan test --parallel

# Run with memory limit
php artisan test --memory-limit=512M

# Run specific suite
php artisan test --testsuite=Feature

# Run and stop on first failure
php artisan test --stop-on-failure
```

### 5.2 Code Quality

```bash
# Run all quality checks
composer quality-check

# Run PHP CS Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Run PHPStan
./vendor/bin/phpstan analyse

# Run Rector (dry-run)
./vendor/bin/rector process --dry-run
```

---

## 6. Production Deployment

### 6.1 Pre-Deployment Checklist

```bash
# 1. Run tests
php artisan test

# 2. Check code quality
composer quality-check

# 3. Sync permissions
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions

# 4. Run migrations (in dry-run first if possible)
php artisan migrate --force
```

### 6.2 Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci

# 3. Run migrations
php artisan migrate --force

# 4. Sync RBAC
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions

# 5. Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Build frontend assets
npm run build

# 7. Restart queue workers
php artisan queue:restart

# 8. Warm Octane cache (if using)
php artisan octane:reload
```

### 6.3 Octane Production Server

```bash
# Start production server
php artisan octane:start --server=frankenphp --port=80 --workers=4 --max-requests=1000

# Reload without downtime
php artisan octane:reload

# Stop server
php artisan octane:stop

# Check status
php artisan octane:status
```

---

## 7. Troubleshooting

### 7.1 Common Issues

**Permission Denied (403)**:
```bash
# Check user roles
php artisan tinker
>>> auth()->user()->getRoleNames()

# Sync role permissions
php artisan rbac:sync-role-permissions

# Clear permission cache
php artisan permission:cache-reset
```

**No Company Context**:
```bash
# Check route has {company} parameter
# Verify identify.company middleware is applied
php artisan route:list | grep invoices
```

**Database Connection Error**:
```bash
# Test connection
php artisan tinker
>>> DB::connection()->getPdo()

# Check .env
DB_HOST=localhost
DB_DATABASE=haasib
DB_USERNAME=postgres
DB_PASSWORD=secret
```

**Migration Failures**:
```bash
# Check migration status
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback --step=1
php artisan migrate

# Fresh start (careful - data loss!)
php artisan migrate:fresh --seed
```

### 7.2 Debug Commands

```bash
# Show all routes
php artisan route:list

# Show routes with middleware
php artisan route:list -v

# Filter routes
php artisan route:list --path=company

# Show config
php artisan config:show database

# Environment check
php artisan env

# Tinker (interactive)
php artisan tinker

# Single command in tinker
php artisan tinker --execute="echo User::count()"
```

### 7.3 Performance Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear

# Rebuild optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize

# Profile route
php artisan route:list --sort=name
```

---

## Quick Reference

### Daily Development

```bash
# Start servers
php artisan octane:start --server=frankenphp --port=9001 --watch
npm run dev

# Run tests
php artisan test

# Check quality
composer quality-check
```

### Adding a Feature

```bash
# 1. Create migration
php artisan make:migration create_invoices_table

# 2. Create model
php artisan make:model Invoice -f

# 3. Create FormRequest
php artisan make:request StoreInvoiceRequest

# 4. Create controller
php artisan make:controller InvoiceController --resource

# 5. Add permissions
# Edit app/Constants/Permissions.php
php artisan rbac:sync-permissions

# 6. Update role-permissions.php
php artisan rbac:sync-role-permissions

# 7. Run migration
php artisan migrate
```

### Production Deployment

```bash
# Full deployment
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
php artisan octane:reload
```

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System overview
- [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md) - Permissions system
- [07-TESTING-QUALITY.md](07-TESTING-QUALITY.md) - Testing standards
