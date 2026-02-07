# CLI & Operations Guide

**Last Updated**: 2025-02-01  
**Purpose**: CLI commands, deployment, and operations  
**Audience**: DevOps and developers

---

## Table of Contents

1. [Development Server](#1-development-server)
2. [Artisan Commands](#2-artisan-commands)
3. [RBAC Commands](#3-rbac-commands)
4. [Database Operations](#4-database-operations)
5. [Testing Commands](#5-testing-commands)
6. [Production Deployment](#6-production-deployment)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. Development Server

### Start Development Environment

```bash
# Terminal 1: Start Laravel Octane (FrankenPHP)
php artisan octane:start --server=frankenphp --port=9001 --watch

# Terminal 2: Start Vite dev server
npm run dev

# Access application
open http://localhost:5180
```

### Environment Setup

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

### Code Generation

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

### Application Maintenance

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

# Schedule run
php artisan schedule:run
```

---

## 3. RBAC Commands

### Permission Management

```bash
# Sync all permissions from config
php artisan rbac:sync-permissions

# Sync role permissions for all companies
php artisan rbac:sync-role-permissions

# Sync for specific company
php artisan rbac:sync-role-permissions --company=<uuid>

# Reset permission cache
php artisan permission:cache-reset
```

### User Management (Tinker)

```bash
# Create super admin
php artisan tinker
>>> $user = User::where('email', 'admin@example.com')->first()
>>> $user->assignRole('super_admin')

# Assign role to user
>>> $user->assignRole('owner')

# Check permissions
>>> $user->can('acct.invoices.create')
```

---

## 4. Database Operations

### Migrations

```bash
# Run all pending migrations
php artisan migrate

# Run specific migration
php artisan migrate --path=database/migrations/2025_01_15_000001_create_table.php

# Rollback last batch
php artisan migrate:rollback

# Rollback specific steps
php artisan migrate:rollback --step=3

# Fresh database (careful!)
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status
```

### Database Seeding

```bash
# Seed with default seeders
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=UserSeeder

# Fresh and seed
php artisan migrate:fresh --seed
```

---

## 5. Testing Commands

### PHPUnit/Pest

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

# Stop on first failure
php artisan test --stop-on-failure
```

### Code Quality

```bash
# Run all quality checks
composer quality-check

# Run PHP CS Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Run PHPStan
./vendor/bin/phpstan analyse
```

---

## 6. Production Deployment

### Pre-Deployment Checklist

```bash
# 1. Run tests
php artisan test

# 2. Check code quality
composer quality-check

# 3. Sync permissions
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions
```

### Deployment Steps

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

# 6. Build frontend
npm run build

# 7. Restart queue workers
php artisan queue:restart

# 8. Reload Octane
php artisan octane:reload
```

### Octane Production Server

```bash
# Start production server
php artisan octane:start --server=frankenphp --port=80 --workers=4

# Reload without downtime
php artisan octane:reload

# Stop server
php artisan octane:stop
```

---

## 7. Troubleshooting

### Permission Denied (403)

```bash
# Check user roles
php artisan tinker
>>> auth()->user()->getRoleNames()

# Sync role permissions
php artisan rbac:sync-role-permissions

# Clear permission cache
php artisan permission:cache-reset
```

### No Company Context

```bash
# Check route has {company} parameter
# Verify identify.company middleware is applied
php artisan route:list | grep invoices
```

### Database Connection

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

### Migration Failures

```bash
# Check status
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback --step=1
php artisan migrate

# Fresh start (careful - data loss!)
php artisan migrate:fresh --seed
```

### Debug Commands

```bash
# Show all routes
php artisan route:list

# Show routes with middleware
php artisan route:list -v

# Show config
php artisan config:show database

# Environment check
php artisan env

# Interactive shell
php artisan tinker
```

---

## Quick Reference

### Daily Development

```bash
php artisan octane:start --server=frankenphp --port=9001 --watch
npm run dev
php artisan test
```

### Adding a Feature

```bash
php artisan make:migration create_table
php artisan make:model ModelName -f
php artisan make:request StoreRequest
php artisan make:controller Controller --resource
# Edit Permissions.php
php artisan rbac:sync-permissions
# Update role-permissions.php
php artisan rbac:sync-role-permissions
```

### Production Deployment

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan rbac:sync-permissions
php artisan rbac:sync-role-permissions
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan octane:reload
```

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System overview
- [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md) - Coding standards
- [03-RBAC-GUIDE.md](03-RBAC-GUIDE.md) - Permissions system
