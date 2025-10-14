# CLI Commands: Initial Platform Setup

## Setup Commands

```bash
# Check setup status
haasib setup:status
haasib status
haasib check

# Initialize system with demo data
haasib setup:init
haasib setup:init --demo-data
haasib setup:init --industries=hospitality,retail,professional
haasib setup:init --force

# Reset system (system owner only)
haasib setup:reset
haasib setup:reset --confirm
haasib setup:clear
```

## User Management Commands

```bash
# List predefined users
haasib users
haasib users:list
haasib user:list

# Switch user context
haasib users:switch <username>
haasib switch <username>
haasub login <username>

# Create new user (system owner only)
haasib users:create
haasib user:create --name="John Doe" --role=accountant

# Check current user
haasib whoami
haasib users:current
```

## Company Management Commands

```bash
# List companies
haasib companies
haasib companies:list

# Switch active company
haasib companies:switch <company-name|company-id>
haasib switch:company <company>
haasib company <company>

# Create new company
haasib companies:create "New Company"
haasib company:create --name="ABC Corp" --industry=retail

# Show company details
haasib companies:show
haasib company:info
```

## Module Management Commands

```bash
# List available modules
haasib modules
haasib modules:list

# Show module status
haasib modules:status
haasib module:status <module-name>

# Enable module for current company
haasib modules:enable <module-name>
haasib module:enable core

# Disable module (with confirmation)
haasib modules:disable <module-name>
haasib module:disable invoicing --confirm

# Install module (if not installed)
haasib modules:install <module-name>
haasib module:install ledger
```

## Demo Data Commands

```bash
# Seed demo data
haasib seed:demo
haasib seed:all
haasib seed --type=demo

# Seed specific company data
haasib seed:company <company-name>
haasib seed:companies hospitality,retail

# Seed specific modules
haasib seed:modules core,ledger,invoicing

# Refresh demo data
haasib seed:refresh
haasib seed:reset
```

## Utility Commands

```bash
# Check system health
haasib health
haasib doctor
haasib check:all

# Show configuration
haasib config
haasib config:show
haasib config:get <key>

# Set configuration
haasib config:set <key> <value>
haasib config:set timezone UTC

# Clear caches
haasib cache:clear
haasib clear:all
haasib optimize:clear

# View audit trail
haasib audit
haasib audit:log
haasub audit:show --user=<id> --company=<id>

# Database operations
haasub db:migrate
haasub db:seed
haasub db:refresh
haasub db:reset --confirm
```

## Natural Language Examples

```bash
# Setup and initialization
haasub "initialize the system"
haasub "set up demo data"
haasub "create sample companies"
haasub "reset everything"

# User and company switching
haasub "switch to John"
haasub "login as accountant"
haasub "switch to retail company"
haasub "show my companies"

# Module management
haasub "enable invoicing module"
haasub "show available modules"
haasub "disable ledger module"
haasub "what modules are enabled"

# Demo data
haasub "seed hospitality data"
haasub "create demo invoices"
haasub "generate sample payments"
haasub "refresh all demo data"
```

## Output Formats

### Setup Status Output
```
┌─────────────────────────────────────┐
│  Haasib Setup Status                 │
├─────────────────────────────────────┤
│  Initialized:  ✅ Yes               │
│  Companies:    3 (hospitality, retail, professional) │
│  Users:        5                    │
│  Modules:      Core, Ledger, Invoicing │
│  Demo Data:    ✅ Complete          │
└─────────────────────────────────────┘
```

### Company List Output
```
Available Companies:
┌─────────────────────────────────────────────────────────────┐
│ ID           │ Name              │ Industry     │ Currency │
├─────────────────────────────────────────────────────────────┤
│ 123e4567-e  │ Grand Hotel       │ Hospitality  │ USD      │
│ 456e7890-e  │ TechMart Store    │ Retail       │ USD      │
│ 789e0123-e  │ Consulting Pro    │ Professional │ USD      │
└─────────────────────────────────────────────────────────────┘
* Active: Grand Hotel (switch with: haasib company switch <name>)
```

### Module Status Output
```
Module Status:
┌─────────────────────────────────────────────────────────────┐
│ Module     │ Status │ Version │ Description                  │
├─────────────────────────────────────────────────────────────┤
│ Core       │ ✅ On  │ v1.0.0  │ Users, companies, auth      │
│ Ledger     │ ✅ On  │ v1.0.0  │ Chart of accounts, journal  │
│ Invoicing  │ ✅ On  │ v1.0.0  │ Invoices, customers, payments│
└─────────────────────────────────────────────────────────────┘
```

## Interactive Mode

Some commands support interactive mode:

```bash
# Interactive company switch
haasub company switch
? Select company: [Use arrows to move, type to filter]
> Grand Hotel (hospitality)
  TechMart Store (retail)
  Consulting Pro (professional)

# Interactive module management
haasub modules manage
? Choose action:
> Enable module
  Disable module
  View details
  Check dependencies

# Interactive demo data seeding
haasub seed demo
? What to seed?
> All demo data
  Specific company
  Specific module
  Custom range
```