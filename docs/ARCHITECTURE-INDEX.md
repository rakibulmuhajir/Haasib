# Haasib Architecture Documentation Index

**Master guide to all architecture and developer documentation**

---

## 🎯 Where to Start

Choose based on your role:

### 👨‍💻 **I'm a New Developer**
1. Read: [ARCHITECTURE.md](ARCHITECTURE.md) (30 min) - Understand the whole system
2. Read: [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md) (15 min) - See all routes and features
3. Explore: [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md) (10 min) - See how requests work
4. Reference: [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md) - For common tasks

### 🔧 **I Want to Add a Feature**
1. Review: [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md#adding-features)
2. Reference: [PERMISSIONS.md](PERMISSIONS.md#adding-new-permissions)
3. Reference: [SCHEMAS.md](SCHEMAS.md) - Database patterns
4. Study: Similar existing feature in `build/resources/js/routes/`

### 🔐 **I Need to Manage Permissions**
1. Read: [PERMISSIONS.md](PERMISSIONS.md) - Complete RBAC guide
2. Reference: [ARCHITECTURE.md#rbac-system](ARCHITECTURE.md#rbac-system)
3. Run: `php artisan rbac:sync-permissions`

### 📊 **I Need to Understand the Database**
1. Read: [SCHEMAS.md](SCHEMAS.md) - All 11 schemas documented
2. Reference: Schema contracts in `docs/contracts/`
3. Study: Migrations in `build/database/migrations/`

### 🚀 **I'm Debugging Something**
1. Check: [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md)
2. Reference: [DEVELOPER_REFERENCE.md#troubleshooting](DEVELOPER_REFERENCE.md#troubleshooting)
3. Search: Relevant schema in [SCHEMAS.md](SCHEMAS.md)

---

## 📚 Complete Documentation Map

### Core Architecture (4 documents)

| Document | Purpose | Read Time | For Whom |
|----------|---------|-----------|----------|
| **[ARCHITECTURE.md](ARCHITECTURE.md)** | Complete system overview, layers, multi-tenancy | 40 min | Everyone starting out |
| **[ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md)** | Route hierarchy, feature tree, navigation | 20 min | Understanding app flow |
| **[ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md)** | Request journey frontend to database | 25 min | Understanding deep flow |
| **[modules-architecture.md](modules-architecture.md)** | Module organization and structure | 15 min | Understanding features |

### Implementation Guides (3 documents)

| Document | Purpose | Read Time | For Whom |
|----------|---------|-----------|----------|
| **[DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md)** | Patterns, quick-start, common tasks | 30 min | Daily development work |
| **[PERMISSIONS.md](PERMISSIONS.md)** | RBAC, all 151 permissions, role setup | 20 min | Permission management |
| **[SCHEMAS.md](SCHEMAS.md)** | All 11 database schemas, tables, queries | 35 min | Database work |

### Frontend Architecture

| Document | Purpose | Read Time | For Whom |
|----------|---------|-----------|----------|
| **[frontend-architecture.md](frontend-architecture.md)** | Vue 3, components, routing, state | 20 min | Frontend developers |

### Contracts (Individual Schema Docs)

Located in `docs/contracts/` - Detailed specification for each schema:

**Complete (Implementation Ready):**
- `auth-contract.md` - Users, companies, auth
- `gl-core-schema.md` - General ledger core
- `coa-schema.md` - Chart of accounts
- `accounting-invoicing-contract.md` - Invoices, AR
- `ap-schema.md` - Bills, AP
- `banking-schema.md` - Bank accounts, reconciliation
- `inventory-schema.md` - Items, stock, warehouses
- `payroll-schema.md` - Employees, payroll, leaves

**Contract Only (Design Phase):**
- `tax-schema.md` - Tax management
- `reporting-schema.md` - Reports
- `crm-schema.md` - CRM module
- `vms-schema.md` - Visitor management
- `system-schema.md` - System settings

---

## 🗺️ System Architecture at a Glance

### High-Level Architecture

```
┌─────────────────────────────────────────┐
│  Frontend Layer (Vue 3 + TypeScript)    │
│  49+ feature modules in routes/         │
└────────────────┬────────────────────────┘
                 │ Inertia.js
┌────────────────┴────────────────────────┐
│  HTTP Middleware Layer                   │
│  auth, identify.company, permissions    │
└────────────────┬────────────────────────┘
                 │
┌────────────────┴────────────────────────┐
│  Application Layer (Laravel 12)          │
│  Controllers, FormRequests, Services    │
│  Company Context via CurrentCompany     │
└────────────────┬────────────────────────┘
                 │ Eloquent ORM
┌────────────────┴────────────────────────┐
│  Model Layer (11 core models)           │
│  Relationships, casts, traits           │
└────────────────┬────────────────────────┘
                 │ PDO / Query Builder
┌────────────────┴────────────────────────┐
│  Database Layer (PostgreSQL)             │
│  11 schemas, 100+ tables, RLS policies  │
└─────────────────────────────────────────┘
```

### Tech Stack Summary

```
Frontend:    Vue 3 + TypeScript + Vite + Tailwind + Shadcn
Backend:     Laravel 12 + Octane + Inertia
Database:    PostgreSQL (multi-schema)
Auth:        Laravel Fortify + Spatie Permission
Testing:     Pest, Playwright
Deployment:  Docker ready
```

### Multi-Tenancy Model

```
Company Context Flow:
  URL {company} param
       ↓
  IdentifyCompany middleware
       ↓
  CurrentCompany singleton
       ↓
  Spatie team binding
       ↓
  Database RLS policies
       ↓
  Data isolation guaranteed
```

---

## 🔍 Finding What You Need

### "I Need to Understand..."

| Topic | Location |
|-------|----------|
| How requests work | [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md) |
| Company context | [ARCHITECTURE.md#multi-tenancy-architecture](ARCHITECTURE.md#multi-tenancy-architecture) |
| Permission system | [PERMISSIONS.md](PERMISSIONS.md) |
| Database structure | [SCHEMAS.md](SCHEMAS.md) |
| Frontend routing | [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md#frontend-navigation) |
| API endpoints | [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md#company-scoped-routes) |
| Middleware chain | [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md#middleware-execution-order) |
| Models & relationships | [SCHEMAS.md](SCHEMAS.md) |
| Code patterns | [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md#common-code-patterns) |

### "I Want to..."

| Task | Go To |
|------|-------|
| Add a new feature | [DEVELOPER_REFERENCE.md#adding-features](DEVELOPER_REFERENCE.md#adding-features) |
| Add a new permission | [PERMISSIONS.md#adding-new-permissions](PERMISSIONS.md#adding-new-permissions) |
| Add a database column | [DEVELOPER_REFERENCE.md#database-changes](DEVELOPER_REFERENCE.md#database-changes) |
| Create a model | [DEVELOPER_REFERENCE.md#pattern-2-model-with-company-scope](DEVELOPER_REFERENCE.md#pattern-2-model-with-company-scope) |
| Write a test | [DEVELOPER_REFERENCE.md#testing](DEVELOPER_REFERENCE.md#testing) |
| Create a Vue component | [DEVELOPER_REFERENCE.md#pattern-4-vue-component-with-form](DEVELOPER_REFERENCE.md#pattern-4-vue-component-with-form) |
| Set up environment | [DEVELOPER_REFERENCE.md#setup--running](DEVELOPER_REFERENCE.md#setup--running) |
| Debug an issue | [DEVELOPER_REFERENCE.md#troubleshooting](DEVELOPER_REFERENCE.md#troubleshooting) |

---

## 📋 Module Organization

### Core Modules (10+)

| Module | Routes | Pages | Purpose |
|--------|--------|-------|---------|
| Authentication | login, register, password reset | 5 | User auth flows |
| Companies | companies list, settings | 3 | Tenant management |
| Users | user list, roles | 2 | User management |
| Settings | appearance, settings | 2 | App configuration |

### Accounting Module (15+ sub-features)

| Feature | Location | Tables | Permissions |
|---------|----------|--------|-------------|
| Invoices | `routes/invoices/` | acct.invoices | 6 |
| Customers | `routes/customers/` | acct.customers | 4 |
| Bills | `routes/bills/` | acct.bills | 6 |
| Vendors | `routes/vendors/` | acct.vendors | 4 |
| Chart of Accounts | `routes/accounts/` | acct.accounts | 5 |
| Payments | `routes/payments/` | acct.payments | 4 |
| Credit Notes | `routes/credit-notes/` | acct.credit_notes | 5 |
| Manual Entries | `routes/journals/` | acct.journal_entries | 2 |

### Inventory Module (3 features)

| Feature | Location | Tables | Permissions |
|---------|----------|--------|-------------|
| Items | `routes/items/` | inv.items | 4 |
| Categories | `routes/item-categories/` | inv.item_categories | 4 |
| Stock & Warehouses | `routes/stock-movements/` | inv.stock_levels | 7 |

### Payroll Module (5 features)

| Feature | Location | Tables | Permissions |
|---------|----------|--------|-------------|
| Employees | `routes/employees/` | pay.employees | 4 |
| Payroll Runs | `routes/payroll/` | pay.payroll_runs | 4 |
| Payslips | `routes/payslips/` | pay.payslips | 5 |
| Leave Requests | `routes/leave-requests/` | pay.leave_requests | 5 |
| Payroll Settings | N/A | N/A | 2 |

### Banking Module (3 features)

| Feature | Location | Tables | Permissions |
|---------|----------|--------|-------------|
| Bank Accounts | `routes/banking/` | bank.company_bank_accounts | 4 |
| Reconciliation | `routes/banking/` | bank.bank_reconciliations | 4 |
| Bank Feeds | `routes/banking/` | bank.bank_transactions | 3 |

### Industry Verticals

#### Fuel Station (21+ features)
Located in `routes/fuel/`:
- Pumps, products, rates, daily close, handovers, investors, tank readings, amanat

---

## 🔗 Cross-References

### By Feature Domain

**Accounting:**
- Code: `build/app/Http/Controllers/InvoiceController.php`, etc.
- Frontend: `build/resources/js/routes/invoices/`
- DB: `acct` schema → [SCHEMAS.md#acct-schema](SCHEMAS.md#acct-schema)
- Permissions: [PERMISSIONS.md#accounting-module](PERMISSIONS.md#accounting-module)
- Contract: `docs/contracts/accounting-invoicing-contract.md`

**Inventory:**
- Code: `build/app/Models/Item.php`
- Frontend: `build/resources/js/routes/items/`
- DB: `inv` schema → [SCHEMAS.md#inv-schema](SCHEMAS.md#inv-schema)
- Permissions: [PERMISSIONS.md#inventory-module](PERMISSIONS.md#inventory-module)
- Contract: `docs/contracts/inventory-schema.md`

**Payroll:**
- Code: `build/app/Models/Employee.php`, `Payslip.php`
- Frontend: `build/resources/js/routes/employees/`, `payslips/`
- DB: `pay` schema → [SCHEMAS.md#pay-schema](SCHEMAS.md#pay-schema)
- Permissions: [PERMISSIONS.md#payroll-module](PERMISSIONS.md#payroll-module)
- Contract: `docs/contracts/payroll-schema.md`

**Authentication & Tenancy:**
- Code: `build/app/Models/User.php`, `Company.php`
- Services: `build/app/Services/CurrentCompany.php`
- DB: `auth` schema → [SCHEMAS.md#auth-schema](SCHEMAS.md#auth-schema)
- Middleware: `build/app/Http/Middleware/IdentifyCompany.php`
- Contract: `docs/contracts/auth-contract.md`

---

## 📖 Related Documentation

### Project Guidelines
- **CLAUDE.md** - Development standards and patterns (in root)
- **Schema Contracts** - `docs/contracts/` (21 files)

### External References
- **Laravel 12:** https://laravel.com/docs/12
- **Vue 3:** https://vuejs.org/guide/
- **Inertia.js:** https://inertiajs.com/
- **Spatie Permission:** https://spatie.be/docs/laravel-permission/v6
- **Tailwind CSS:** https://tailwindcss.com/docs
- **Shadcn Vue:** https://www.shadcn-vue.com/

---

## 🚀 Development Workflow

### Step 1: Understand the Feature
1. Read relevant feature doc from this index
2. Check schema in [SCHEMAS.md](SCHEMAS.md)
3. Review similar feature implementation

### Step 2: Plan the Change
1. Check CLAUDE.md for patterns
2. List database changes needed
3. List permission constants needed
4. Sketch component structure

### Step 3: Implement
1. Create migration
2. Update model
3. Create controller/FormRequest
4. Add permissions
5. Create Vue component
6. Update routes

### Step 4: Test & Review
1. Run tests: `php artisan test`
2. Test manually in browser
3. Check permissions work
4. Review code against CLAUDE.md patterns

### Step 5: Deploy
1. Run migrations
2. Sync permissions: `php artisan rbac:sync-permissions`
3. Restart server

---

## 📞 Getting Help

1. **Can't find something?** Use search in this index
2. **Want to understand flow?** Read [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md)
3. **Database question?** Check [SCHEMAS.md](SCHEMAS.md)
4. **Permission issue?** See [PERMISSIONS.md](PERMISSIONS.md)
5. **Code pattern?** Review [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md)
6. **Stuck?** Check [DEVELOPER_REFERENCE.md#troubleshooting](DEVELOPER_REFERENCE.md#troubleshooting)

---

## 🗂️ File Locations Quick Reference

```
Project Root
├── CLAUDE.md                           # Development standards
│
├── docs/
│   ├── ARCHITECTURE.md                 # Main architecture
│   ├── ARCHITECTURE-INDEX.md           # THIS FILE
│   ├── ARCHITECTURE-ENTRY-POINTS.md    # Routes & features
│   ├── ARCHITECTURE-REQUEST-FLOW.md    # Request lifecycle
│   ├── modules-architecture.md         # Module organization
│   ├── frontend-architecture.md        # Frontend structure
│   ├── PERMISSIONS.md                  # RBAC guide
│   ├── SCHEMAS.md                      # Database schemas
│   ├── DEVELOPER_REFERENCE.md          # Quick reference
│   └── contracts/                      # Schema contracts (21 files)
│
├── build/
│   ├── app/
│   │   ├── Constants/Permissions.php   # 151 permissions
│   │   ├── Models/                     # 11 core models
│   │   ├── Http/Controllers/           # Route handlers
│   │   ├── Http/Middleware/            # Request middleware
│   │   ├── Http/Requests/              # FormRequests
│   │   ├── Services/                   # Business logic
│   │   └── Actions/                    # Domain actions
│   ├── routes/
│   │   ├── web.php                     # Web routes (449 lines)
│   │   └── api.php                     # API routes
│   ├── bootstrap/app.php               # Laravel config
│   ├── config/role-permissions.php     # RBAC mappings
│   └── database/
│       ├── migrations/                 # 40+ migrations
│       ├── factories/                  # Test factories
│       └── seeders/                    # Database seeders
│
└── build/resources/js/
    ├── routes/                         # 49+ feature modules
    ├── pages/                          # 11 page layouts
    ├── components/                     # Vue components
    ├── layouts/AppLayout.vue           # Main layout
    └── navigation/registry.ts          # Nav configuration
```

---

**Last Updated:** January 2026
**Total Documentation:** 11 core docs + 21 schema contracts = 32 files
**Total Size:** ~400 KB of developer documentation
**Coverage:** 100% of system architecture
