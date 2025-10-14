# CLI Contracts - Company Registration Multi-Company Creation

**Feature**: Company Registration - Multi-Company Creation  
**Date**: 2025-10-07  

## Company CLI Commands

### Create Company
```bash
php artisan company:create \
  --name="My Business LLC" \
  --currency="USD" \
  --timezone="America/New_York" \
  [--country="US"] \
  [--language="en"] \
  [--locale="en_US"]

Options:
  --name         Company name (required)
  --currency     3-letter ISO currency code (required)
  --timezone     Valid timezone identifier (required)
  --country      2-letter ISO country code (optional)
  --language     Language code (default: en)
  --locale       Locale code (default: en_US)
  --user         User ID (default: current authenticated user)

Output:
✓ Company "My Business LLC" created successfully
  ID: 550e8400-e29b-41d4-a716-446655440000
  Slug: my-business-llc
  Currency: USD
  Timezone: America/New_York
  
✓ Fiscal year "2025" created with 12 monthly periods
✓ Chart of accounts created with 25 default accounts
```

### List Companies
```bash
php artisan company:list [--user=USER_ID] [--active] [--include=role,fiscal_year]

Options:
  --user         Filter by user ID (default: current user)
  --active       Show only active companies
  --include      Include additional data (comma-separated)

Output:
┌───────────────────────────────────────┬─────────────────┬──────────┬─────────────┬───────────┐
│ Name                                  │ Slug            │ Currency │ Role        │ Created   │
├───────────────────────────────────────┼─────────────────┼──────────┼─────────────┼───────────┤
│ My Business LLC                       │ my-business-llc │ USD      │ owner       │ 2 days ago│
│ Consulting Co                         │ consulting-co   │ EUR      │ admin       │ 1 week ago│
└───────────────────────────────────────┴─────────────────┴──────────┴─────────────┴───────────┘

Total: 2 companies
```

### Show Company Details
```bash
php artisan company:show {slug|id}

Output:
Company: My Business LLC
ID: 550e8400-e29b-41d4-a716-446655440000
Slug: my-business-llc
Status: Active
Currency: USD
Timezone: America/New_York
Country: US
Language: en
Locale: en_US
Created: 2 days ago
Created by: John Doe (john@example.com)

Current Fiscal Year: 2025 (Jan 1 - Dec 31)
Chart of Accounts: Standard Accounts (25 accounts)
Members: 3 users

User Role: owner
```

### Switch Company Context
```bash
php artisan company:switch {slug|id}

Output:
✓ Switched to company "My Business LLC"
  Context: Owner role
  Fiscal Year: 2025
  Currency: USD
```

## User Management Commands

### Invite User to Company
```bash
php artisan company:invite \
  {company} \
  --email="user@example.com" \
  --role="accountant" \
  [--expires-in-days=7] \
  [--message="Welcome to our team!"]

Arguments:
  company       Company ID or slug

Options:
  --email           User email address (required)
  --role            Role to assign (required: owner|admin|accountant|viewer)
  --expires-in-days Invitation expiry in days (default: 7)
  --message         Custom invitation message
  --invited-by      User ID sending invitation (default: current user)

Output:
✓ Invitation sent to user@example.com
  Company: My Business LLC
  Role: accountant
  Expires: 7 days
  Token: 7a9b1c2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d
```

### List Invitations
```bash
php artisan company:invitations {company} [--status=pending|accepted|rejected|expired]

Output:
┌─────────────────────┬──────────────────┬──────────┬─────────────┬────────────┐
│ Email               │ Role             │ Status   │ Invited By  │ Expires    │
├─────────────────────┼──────────────────┼──────────┼─────────────┼────────────┤
│ user@example.com    │ accountant       │ pending  │ John Doe    │ 5 days     │
│ jane@company.com    │ viewer           │ accepted │ John Doe    │ -          │
└─────────────────────┴──────────────────┴──────────┴─────────────┴────────────┘

Total: 2 invitations (1 pending, 1 accepted)
```

### Assign User to Company
```bash
php artisan company:assign {company} {user} --role="admin"

Arguments:
  company       Company ID or slug
  user          User ID or email

Options:
  --role         Role to assign (required: owner|admin|accountant|viewer)
  --assigned-by  User ID making assignment (default: current user)

Output:
✓ User jane@example.com assigned to "My Business LLC"
  Role: admin
  Assigned by: John Doe
```

### Update User Role
```bash
php artisan company:role:update {company} {user} --role="accountant"

Arguments:
  company       Company ID or slug
  user          User ID or email

Options:
  --role         New role (required: owner|admin|accountant|viewer)
  --updated-by   User ID making change (default: current user)

Output:
✓ Role updated for jane@example.com in "My Business LLC"
  Previous role: admin
  New role: accountant
  Updated by: John Doe
```

### Remove User from Company
```bash
php artisan company:remove {company} {user} [--confirm]

Arguments:
  company       Company ID or slug
  user          User ID or email

Options:
  --confirm      Skip confirmation prompt
  --removed-by   User ID making removal (default: current user)

Output:
⚠️  Remove jane@example.com from "My Business LLC"? This cannot be undone. [y/N] y

✓ User jane@example.com removed from "My Business LLC"
  Previous role: accountant
  Removed by: John Doe
```

## Company Members Commands

### List Company Members
```bash
php artisan company:members {company} [--role=ROLE] [--include=invited_by]

Output:
┌─────────────────────┬──────────────────┬─────────────┬─────────────┬────────────┐
│ Name                │ Email            │ Role        │ Invited By  │ Joined     │
├─────────────────────┼──────────────────┼─────────────┼─────────────┼────────────┤
│ John Doe            │ john@example.com │ owner       │ -           │ 2 days ago │
│ Jane Smith          │ jane@company.com │ admin       │ John Doe    │ 1 week ago │
│ Bob Johnson         │ bob@example.com  │ accountant  │ John Doe    │ 3 days ago │
└─────────────────────┴──────────────────┴─────────────┴─────────────┴────────────┘

Total: 3 members
```

### Check User Access
```bash
php artisan company:access {user} [--company=COMPANY]

Output:
User: john@example.com

Global Access:
- Total companies: 2
- Owner in: 1 company
- Admin in: 1 company

Company Access:
✓ My Business LLC - owner (full access)
✓ Consulting Co - admin (manage access)

Pending Invitations:
1. Acme Corp - accountant (expires in 5 days)
```

## Fiscal Year Commands

### Create Fiscal Year
```bash
php artisan fiscal-year:create {company} \
  --name="2026" \
  --start="2026-01-01" \
  --end="2026-12-31" \
  [--create-periods] \
  [--periods-count=12]

Arguments:
  company       Company ID or slug

Options:
  --name            Fiscal year name (required)
  --start           Start date (required, YYYY-MM-DD)
  --end             End date (required, YYYY-MM-DD)
  --create-periods  Create accounting periods (default: true)
  --periods-count   Number of periods (default: 12)

Output:
✓ Fiscal year "2026" created for "My Business LLC"
  Period: Jan 1, 2026 - Dec 31, 2026
  Status: Current
  Periods created: 12 monthly periods
```

### List Fiscal Years
```bash
php artisan fiscal-year:list {company}

Output:
┌─────────────┬─────────────────────┬─────────────────────┬──────────┬────────────┐
│ Name        │ Period              │ Status              │ Periods  │ Created    │
├─────────────┼─────────────────────┼─────────────────────┼──────────┼────────────┤
│ 2025        │ Jan 1 - Dec 31      │ Current             │ 12       │ 2 days ago │
│ 2024        │ Jan 1 - Dec 31      │ Locked              │ 12       │ 1 year ago │
└─────────────┴─────────────────────┴─────────────────────┴──────────┴────────────┘
```

## Chart of Accounts Commands

### Create Chart of Accounts
```bash
php artisan chart:create {company} \
  --name="Standard Chart" \
  [--template=basic|detailed|industry_specific] \
  [--description="Standard account structure"]

Arguments:
  company       Company ID or slug

Options:
  --name            Chart name (required)
  --template        Use predefined template
  --description     Chart description

Output:
✓ Chart of accounts "Standard Chart" created for "My Business LLC"
  Template: basic
  Accounts created: 25
  Groups created: 7
```

### List Charts of Accounts
```bash
php artisan chart:list {company}

Output:
┌─────────────────────┬─────────────────┬──────────┬─────────────┬────────────┐
│ Name                │ Description     │ Accounts │ Is Template │ Created    │
├─────────────────────┼─────────────────┼──────────┼─────────────┼────────────┤
│ Standard Chart      │ Standard acc... │ 25       │ No          │ 2 days ago │
│ Detailed Chart      │ Detailed acc... │ 45       │ No          │ 1 week ago │
└─────────────────────┴─────────────────┴──────────┴─────────────┴────────────┘
```

## Global Context Commands

### Get Current Context
```bash
php artisan context:current [--format=table|json]

Output:
Current Context:
┌───────────────────────────────────────┐
│ Company: My Business LLC               │
│ Role: owner                            │
│ Fiscal Year: 2025                      │
│ Currency: USD                          │
└───────────────────────────────────────┘
```

### Context Status
```bash
php artisan context:status

Output:
Authentication: ✓ Authenticated as john@example.com
Company Context: ✓ Set to My Business LLC (owner)
Fiscal Year: ✓ Set to 2025 (current)
Permissions: ✓ All company permissions available
```

## Error Handling

### Validation Errors
```bash
php artisan company:create --name="Test" --currency="INVALID"

✗ Error: Invalid currency code. Must be a 3-letter ISO code.
```

### Authorization Errors
```bash
php artisan company:invite my-company --email="test@example.com" --role="owner"

✗ Error: You do not have permission to invite users as 'owner' role.
Required permission: companies.invite_users
```

### Business Logic Errors
```bash
php artisan company:assign my-company existing@user.com --role="admin"

✗ Error: User already has a role in this company.
Current role: viewer
```

## Interactive Mode

All commands support interactive mode when required arguments are omitted:
```bash
php artisan company:create

? Company name: My New Business
? Currency [USD]: ▼
  USD
  EUR
  GBP
? Timezone [America/New_York]: America/New_York
? Country [US]: ▼
  US
  CA
  GB

✓ Company "My New Business" created successfully
```