# Quickstart Guide - Company Registration Multi-Company Creation

**Feature**: Company Registration - Multi-Company Creation  
**Date**: 2025-10-07  
**Target Audience**: Developers implementing the feature  

## Prerequisites

- Laravel 12 with PHP 8.2+
- PostgreSQL 16 with RLS enabled
- Spatie Laravel Permission package
- Vue 3 + Inertia.js v2 + PrimeVue v4
- Existing user authentication system

## Implementation Overview

This feature enables users to create and manage multiple companies with fiscal years, chart of accounts, and role-based user management.

## Key Components

### 1. Database Setup
Run migrations in order:
1. Company tables (`auth.companies`, `auth.company_user`)
2. Fiscal year tables (`accounting.fiscal_years`, `accounting.accounting_periods`)
3. Chart of accounts tables (`accounting.chart_of_accounts`, `accounting.accounts`)
4. Invitation system (`auth.company_invitations`)

### 2. Command Bus Actions
Create these command actions:
- `CompanyCreate` - Create new company
- `CompanyAssign` - Assign user to company with role
- `CompanyInvite` - Invite user to company
- `FiscalYearCreate` - Create fiscal year for company
- `ChartOfAccountsCreate` - Create chart of accounts template

### 3. Controllers
- `CompanyController` - Company CRUD operations
- `CompanyRoleController` - User role management
- `CompanySwitchController` - Context switching
- `CompanyInviteController` - Invitation management

### 4. CLI Commands
- `company:create` - Create company via CLI
- `company:assign` - Assign user to company
- `company:invite` - Send company invitation
- `company:switch` - Switch active company context

## Setup Steps

### Step 1: Run Migrations
```bash
php artisan migrate
```

### Step 2: Seed System Data
```bash
php artisan db:seed --class=AccountingSeeder
```

### Step 3: Register Commands
Add to `bootstrap/app.php`:
```php
->withCommands([
    App\Console\Commands\Company\CreateCompany::class,
    App\Console\Commands\Company\AssignUser::class,
    App\Console\Commands\Company\InviteUser::class,
])
```

### Step 4: Configure Routes
Add routes in `routes/web.php` and `routes/api.php` for company management.

## Usage Examples

### Creating a Company (Web)
```php
// Via CompanyController
$company = CompanyCreate::run([
    'name' => 'My Business LLC',
    'currency' => 'USD',
    'timezone' => 'America/New_York',
    'created_by' => $user->id
]);
```

### Creating a Company (CLI)
```bash
php artisan company:create "My Business LLC" --currency=USD --timezone="America/New_York"
```

### Inviting a User
```php
CompanyInvite::run([
    'company_id' => $company->id,
    'email' => 'user@example.com',
    'role' => 'accountant',
    'invited_by' => $owner->id
]);
```

### Switching Company Context
```php
ServiceContext::setCompany($company->id);
// Or via CLI:
php artisan company:switch --company=$company->slug
```

## Testing

Run the test suite:
```bash
php artisan test --filter=CompanyTest
php artisan test --filter=CompanyRoleTest  
php artisan test --filter=CompanyInvitationTest
```

## Key Files to Implement

- `app/Models/Company.php`
- `app/Models/FiscalYear.php`
- `app/Models/AccountingPeriod.php`
- `app/Actions/Company/CompanyCreate.php`
- `app/Http/Controllers/CompanyController.php`
- `app/Console/Commands/Company/CreateCompany.php`
- `resources/js/Pages/Companies/Index.vue`
- `resources/js/Pages/Companies/Create.vue`

## Permissions Required

Define these permissions in `database/seeders/RbacSeeder.php`:
- `companies.create` - Create new companies
- `companies.view` - View company information
- `companies.manage` - Manage company settings
- `companies.assign_users` - Assign users to company
- `companies.invite_users` - Invite users to company

## RLS Policies

All tables include Row Level Security policies ensuring:
- Users only see companies they belong to
- Company data is strictly isolated
- Role-based access to sensitive operations

## Next Steps

1. Implement the database migrations
2. Create the command bus actions
3. Build the controllers and routes
4. Develop the Vue components
5. Add comprehensive tests
6. Update documentation

## Support

Refer to the legacy implementation in `/app` for reference patterns and existing functionality.