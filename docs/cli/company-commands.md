# Company CLI Commands Documentation

## Overview

The Haasib CLI provides comprehensive command-line tools for managing multi-company operations. All commands support interactive and non-interactive modes.

## Installation

```bash
# Install Haasib CLI globally
composer global require haasib/cli

# Or use via project
php artisan company:help
```

## Commands

### company:create

Create a new company.

```bash
php artisan company:create
```

#### Options

| Option | Description | Required |
|--------|-------------|----------|
| `--name` | Company name | Yes |
| `--currency` | Base currency (3-letter ISO code) | Yes |
| `--country` | Country code (2-letter ISO) | No |
| `--timezone` | Timezone | No |
| `--language` | Language code | No |
| `--locale` | Locale (default: en_US) | No |
| `--interactive` | Interactive mode | No |

#### Interactive Mode

```bash
php artisan company:create --interactive
```

**Example Dialog:**
```
? Company name: Tech Solutions Arabia
? Base currency (USD, EUR, SAR, etc.): SAR
? Country code (SA, US, etc.): SA
? Timezone: Asia/Riyadh
? Language: en
? Locale (en_US): en_US
? Create fiscal year? Yes
? Create chart of accounts? Yes

✓ Company "Tech Solutions Arabia" created successfully!
  ID: 550e8400-e29b-41d4-a716-446655440000
  Slug: tech-solutions-arabia
  Currency: SAR
  Country: SA

  Next steps:
  1. Add users: php artisan company:invite --company=550e8400-e29b-41d4-a716-446655440000
  2. Switch context: php artisan company:switch 550e8400-e29b-41d4-a716-446655440000
  3. View details: php artisan company:show 550e8400-e29b-41d4-a716-446655440000
```

#### Non-Interactive Mode

```bash
php artisan company:create \
  --name="Tech Solutions Arabia" \
  --currency="SAR" \
  --country="SA" \
  --timezone="Asia/Riyadh" \
  --language="en"
```

#### Examples

```bash
# Create US-based company
php artisan company:create \
  --name="Global Tech Inc" \
  --currency="USD" \
  --country="US" \
  --timezone="America/New_York"

# Create EU-based company with custom settings
php artisan company:create \
  --name="European Solutions GmbH" \
  --currency="EUR" \
  --country="DE" \
  --timezone="Europe/Berlin" \
  --language="de" \
  --locale="de_DE"

# Create minimal company (will prompt for required fields)
php artisan company:create --name="Quick Start Ltd"
```

### company:list

List all companies accessible to the current user.

```bash
php artisan company:list
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--all` | Show all companies including inactive | false |
| `--format` | Output format (table, json, csv) | table |
| `--limit` | Limit results | 50 |
| `--sort` | Sort field (name, created_at, updated_at) | name |
| `--order` | Sort direction (asc, desc) | asc |

#### Examples

```bash
# List all active companies
php artisan company:list

# List all companies including inactive
php artisan company:list --all

# List in JSON format
php artisan company:list --format=json

# List sorted by creation date
php artisan company:list --sort=created_at --order=desc

# Limit to 10 results
php artisan company:list --limit=10
```

#### Sample Output

```
+--------------------------------------+-----------------------+----------+---------+-------------------+
| ID                                   | Name                  | Currency | Country | Created At        |
+--------------------------------------+-----------------------+----------+---------+-------------------+
| 550e8400-e29b-41d4-a716-446655440000 | Tech Solutions Arabia | SAR      | SA      | 2025-10-12 10:30  |
| 6b1e9a8f-d7c2-48e5-9f2a-3a5b6c7d8e9f | Global Tech Inc        | USD      | US      | 2025-10-11 15:45  |
| 7a2f8b9g-e8d3-59f6-ag3b-4b6c7d8e9fa0 | EU Solutions GmbH      | EUR      | DE      | 2025-10-10 09:20  |
+--------------------------------------+-----------------------+----------+---------+-------------------+
```

### company:show

Display detailed information about a company.

```bash
php artisan company:show {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--format` | Output format (table, json) | table |
| `--with-users` | Include company users | false |
| `--with-fiscal-years` | Include fiscal years | false |

#### Examples

```bash
# Show basic company info
php artisan company:show 550e8400-e29b-41d4-a716-446655440000

# Show with users
php artisan company:show 550e8400-e29b-41d4-a716-446655440000 --with-users

# Show in JSON format
php artisan company:show 550e8400-e29b-41d4-a716-446655440000 --format=json

# Show complete details
php artisan company:show 550e8400-e29b-41d4-a716-446655440000 \
  --with-users \
  --with-fiscal-years \
  --format=json
```

#### Sample Output

```
Company Details
================
ID:           550e8400-e29b-41d4-a716-446655440000
Name:         Tech Solutions Arabia
Slug:         tech-solutions-arabia
Currency:     SAR
Country:      SA
Timezone:     Asia/Riyadh
Language:     en
Locale:       en_US
Status:       Active
Created:      2025-10-12 10:30:15
Updated:      2025-10-12 11:45:22

Settings:
├── features: accounting, reporting, invoicing
├── preferences: theme=light, timezone=Asia/Riyadh
└── limits: max_users=100, max_storage=10GB

Users (3):
├── admin@example.com (Owner) - Active
├── john@example.com (Admin) - Active
└── sarah@example.com (Accountant) - Active

Fiscal Years (2):
├── 2024 (2024-01-01 to 2024-12-31) - Current
└── 2025 (2025-01-01 to 2025-12-31) - Locked
```

### company:invite

Invite a user to join a company.

```bash
php artisan company:invite {company_id} {email}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--role` | User role (owner, admin, accountant, manager, employee, viewer) | viewer |
| `--message` | Custom invitation message | null |
| `--expires-in` | Expiration in days | 7 |
| `--force` | Skip existing member check | false |

#### Examples

```bash
# Basic invitation
php artisan company:invite 550e8400-e29b-41d4-a716-446655440000 john@example.com

# Invite with specific role
php artisan company:invite 550e8400-e29b-41d4-a716-446655440000 sarah@example.com \
  --role=accountant

# Invite with custom message and expiration
php artisan company:invite 550e8400-e29b-41d4-a716-446655440000 mike@example.com \
  --role=admin \
  --message="Welcome to our team! Please accept this invitation to join Tech Solutions Arabia." \
  --expires-in=14

# Force re-invitation
php artisan company:invite 550e8400-e29b-41d4-a716-446655440000 john@example.com \
  --force
```

#### Sample Output

```
✓ Invitation sent successfully!
  Email: john@example.com
  Role: viewer
  Company: Tech Solutions Arabia (550e8400-e29b-41d4-a716-446655440000)
  Expires: 2025-10-19 10:30:15
  Token: a1b2c3d4e5f6...

  Invitation link: https://app.haasib.com/invitations/a1b2c3d4e5f6...
  
  Next steps:
  1. Share the invitation link with john@example.com
  2. Monitor invitation status: php artisan company:invitations 550e8400-e29b-41d4-a716-446655440000
```

### company:invitations

List company invitations.

```bash
php artisan company:invitations {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--status` | Filter by status (pending, accepted, rejected, expired) | all |
| `--role` | Filter by role | all |
| `--format` | Output format (table, json) | table |

#### Examples

```bash
# List all invitations
php artisan company:invitations 550e8400-e29b-41d4-a716-446655440000

# List pending invitations only
php artisan company:invitations 550e8400-e29b-41d4-a716-446655440000 --status=pending

# List admin role invitations
php artisan company:invitations 550e8400-e29b-41d4-a716-446655440000 --role=admin

# Export to JSON
php artisan company:invitations 550e8400-e29b-41d4-a716-446655440000 --format=json
```

#### Sample Output

```
Company Invitations - Tech Solutions Arabia
==========================================

+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+
| ID                                   | Email             | Role     | Status   | Expires At           | Created By      |
+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+
| a1b2c3d4-e5f6-7890-abcd-ef1234567890 | john@example.com  | viewer   | pending  | 2025-10-19 10:30:15 | admin@example.com |
| b2c3d4e5-f6g7-8901-bcde-f2345678901 | sarah@example.com  | accountant| accepted | 2025-10-18 14:20:30 | admin@example.com |
| c3d4e5f6-g7h8-9012-cdef-345678901234 | mike@example.com   | admin    | rejected | 2025-10-20 09:15:45 | admin@example.com |
+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+

Summary:
├── Total: 3 invitations
├── Pending: 1
├── Accepted: 1
├── Rejected: 1
└── Expired: 0
```

### company:members

List company members.

```bash
php artisan company:members {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--role` | Filter by role | all |
| `--status` | Filter by status (active, inactive) | all |
| `--format` | Output format (table, json) | table |
| `--search` | Search by name or email | null |

#### Examples

```bash
# List all members
php artisan company:members 550e8400-e29b-41d4-a716-446655440000

# List active members only
php artisan company:members 550e8400-e29b-41d4-a716-446655440000 --status=active

# List admin role members
php artisan company:members 550e8400-e29b-41d4-a716-446655440000 --role=admin

# Search members
php artisan company:members 550e8400-e29b-41d4-a716-446655440000 --search=john
```

#### Sample Output

```
Company Members - Tech Solutions Arabia
======================================

+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+
| ID                                   | Name              | Email    | Role     | Status               | Joined At       |
+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+
| 110e8400-f29b-41d4-a716-446655440001 | Admin User        | admin@example.com | owner    | Active               | 2025-10-12 10:30 |
| 220f8410-g29c-51d4-b716-446655440002 | John Smith        | john@example.com  | viewer   | Active               | 2025-10-12 11:15 |
| 33108420-h29d-61d4-c716-446655440003 | Sarah Johnson     | sarah@example.com | accountant| Active               | 2025-10-12 14:20 |
+--------------------------------------+-------------------+----------+----------+----------------------+-----------------+

Summary:
├── Total: 3 members
├── Active: 3
├── Inactive: 0
└── Roles: 1 owner, 1 viewer, 1 accountant
```

### company:switch

Switch the current company context.

```bash
php artisan company:switch {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--global` | Set as global default company | false |
| `--verify` | Verify switch was successful | true |

#### Examples

```bash
# Switch to specific company
php artisan company:switch 550e8400-e29b-41d4-a716-446655440000

# Switch and set as global default
php artisan company:switch 550e8400-e29b-41d4-a716-446655440000 --global

# Switch without verification (faster)
php artisan company:switch 550e8400-e29b-41d4-a716-446655440000 --verify=false
```

#### Sample Output

```
✓ Company context switched successfully!
  
Current Company:
├── ID: 550e8400-e29b-41d4-a716-446655440000
├── Name: Tech Solutions Arabia
├── Role: owner
├── Currency: SAR
├── Fiscal Year: 2025 (2025-01-01 to 2025-12-31)
└── Permissions: company.manage, users.manage, accounting.manage

Available Companies:
├── 550e8400-e29b-41d4-a716-446655440000 - Tech Solutions Arabia (current)
├── 6b1e9a8f-d7c2-48e5-9f2a-3a5b6c7d8e9f - Global Tech Inc
└── 7a2f8b9g-e8d3-59f6-ag3b-4b6c7d8e9fa0 - EU Solutions GmbH

Next commands:
├── List companies: php artisan company:list
├── View details: php artisan company:show 550e8400-e29b-41d4-a716-446655440000
├── Invite users: php artisan company:invite 550e8400-e29b-41d4-a716-446655440000 user@example.com
└── Switch company: php artisan company:switch {company_id}
```

### company:update

Update company details.

```bash
php artisan company:update {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--name` | Company name | current |
| `--currency` | Base currency | current |
| `--country` | Country code | current |
| `--timezone` | Timezone | current |
| `--language` | Language | current |
| `--locale` | Locale | current |
| `--active` | Set active status | current |
| `--interactive` | Interactive mode | false |

#### Examples

```bash
# Interactive update
php artisan company:update 550e8400-e29b-41d4-a716-446655440000 --interactive

# Update specific fields
php artisan company:update 550e8400-e29b-41d4-a716-446655440000 \
  --name="Tech Solutions KSA" \
  --timezone="Asia/Riyadh"

# Deactivate company
php artisan company:update 550e8400-e29b-41d4-a716-446655440000 --active=false
```

### company:delete

Delete a company.

```bash
php artisan company:delete {company_id}
```

#### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--force` | Skip confirmation | false |
| `--backup` | Create backup before deletion | true |

#### Examples

```bash
# Delete with confirmation
php artisan company:delete 550e8400-e29b-41d4-a716-446655440000

# Force delete without confirmation
php artisan company:delete 550e8400-e29b-41d4-a716-446655440000 --force

# Delete with backup
php artisan company:delete 550e8400-e29b-41d4-a716-446655440000 --backup
```

## Workflows

### Complete Company Setup

```bash
# 1. Create company
php artisan company:create \
  --name="Tech Solutions Arabia" \
  --currency="SAR" \
  --country="SA"

# 2. Switch context
php artisan company:switch {company_id}

# 3. Invite team members
php artisan company:invite {company_id} admin1@example.com --role=admin
php artisan company:invite {company_id} accountant1@example.com --role=accountant
php artisan company:invite {company_id} employee1@example.com --role=employee

# 4. Monitor invitations
php artisan company:invitations {company_id} --status=pending

# 5. View final setup
php artisan company:show {company_id} --with-users
```

### Bulk Operations

```bash
# Export companies to CSV
php artisan company:list --format=csv > companies.csv

# Export invitations
php artisan company:invitations {company_id} --format=json > invitations.json

# Export members
php artisan company:members {company_id} --format=json > members.json
```

### Maintenance Tasks

```bash
# Check all accessible companies
php artisan company:list --all

# Review pending invitations
for company in $(php artisan company:list --format=json | jq -r '.data[].id'); do
    php artisan company:invitations $company --status=pending
done

# Update company settings
php artisan company:update {company_id} --interactive
```

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   # Check current context
   php artisan company:context
   
   # Switch to correct company
   php artisan company:switch {company_id}
   ```

2. **Company Not Found**
   ```bash
   # List accessible companies
   php artisan company:list
   
   # Check if company is inactive
   php artisan company:list --all
   ```

3. **Invitation Failed**
   ```bash
   # Check user permissions
   php artisan company:show {company_id} --with-users
   
   # Verify email format
   php artisan company:invite {company_id} test@example.com --role=viewer
   ```

### Debug Mode

Enable debug output for troubleshooting:

```bash
export HAASIB_DEBUG=1
php artisan company:create --name="Test Company"
```

### Logs

Check application logs for detailed error information:

```bash
tail -f storage/logs/laravel.log | grep -i company
```

## Configuration

### Environment Variables

```bash
# Company settings
COMPANY_DEFAULT_CURRENCY=SAR
COMPANY_DEFAULT_TIMEZONE=Asia/Riyadh
COMPANY_DEFAULT_LANGUAGE=en
COMPANY_DEFAULT_LOCALE=en_US

# Invitation settings
INVITATION_DEFAULT_EXPIRY_DAYS=7
INVITATION_MAX_EXPIRY_DAYS=30

# CLI settings
CLI_DEFAULT_OUTPUT_FORMAT=table
CLI_PAGINATION_LIMIT=50
```

### Config File

`config/company.php`:

```php
<?php

return [
    'default_currency' => env('COMPANY_DEFAULT_CURRENCY', 'USD'),
    'default_timezone' => env('COMPANY_DEFAULT_TIMEZONE', 'UTC'),
    'default_language' => env('COMPANY_DEFAULT_LANGUAGE', 'en'),
    'default_locale' => env('COMPANY_DEFAULT_LOCALE', 'en_US'),
    
    'invitation' => [
        'default_expiry_days' => env('INVITATION_DEFAULT_EXPIRY_DAYS', 7),
        'max_expiry_days' => env('INVITATION_MAX_EXPIRY_DAYS', 30),
    ],
    
    'cli' => [
        'default_output_format' => env('CLI_DEFAULT_OUTPUT_FORMAT', 'table'),
        'pagination_limit' => env('CLI_PAGINATION_LIMIT', 50),
    ],
];
```