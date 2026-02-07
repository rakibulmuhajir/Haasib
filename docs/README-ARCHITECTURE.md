# 📖 Haasib Architecture Documentation

Complete system architecture documentation for developer onboarding and heavy changes.

## 🚀 Quick Start

**New to the codebase?** Start here:

1. **[ARCHITECTURE-INDEX.md](ARCHITECTURE-INDEX.md)** (10 min) - Choose your path based on role
2. **[ARCHITECTURE.md](ARCHITECTURE.md)** (30 min) - Complete system overview
3. **[DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md)** (ongoing) - Reference for common tasks

## 📚 Documentation Files

### Core System Understanding

| File | Purpose | Time |
|------|---------|------|
| **ARCHITECTURE.md** | System overview, layers, multi-tenancy | 40 min |
| **ARCHITECTURE-INDEX.md** | Navigation hub and quick reference | 10 min |
| **ARCHITECTURE-ENTRY-POINTS.md** | Routes, features, navigation | 20 min |
| **ARCHITECTURE-REQUEST-FLOW.md** | Request lifecycle, middleware chain | 25 min |

### Implementation & Reference

| File | Purpose | Time |
|------|---------|------|
| **DEVELOPER_REFERENCE.md** | Setup, patterns, common tasks, troubleshooting | 30 min |
| **PERMISSIONS.md** | RBAC system, all 151 permissions, role setup | 20 min |
| **SCHEMAS.md** | Database schemas, tables, relationships, queries | 35 min |

### Module Documentation

| File | Purpose |
|------|---------|
| **modules-architecture.md** | Module organization |
| **frontend-architecture.md** | Frontend structure, components, routing |

### Schema Contracts

Located in `contracts/` directory - detailed specifications for each schema:

**Complete (Implementation Ready):**
- `auth-contract.md` - Authentication & tenancy
- `gl-core-schema.md` - General ledger
- `coa-schema.md` - Chart of accounts
- `accounting-invoicing-contract.md` - Invoices & AR
- `ap-schema.md` - Bills & AP
- `banking-schema.md` - Bank management
- `inventory-schema.md` - Inventory & stock
- `payroll-schema.md` - Payroll & HR

**Contract Only:**
- `tax-schema.md`, `reporting-schema.md`, `crm-schema.md`, `vms-schema.md`, `system-schema.md`

## 🎯 Navigation by Role

### 👨‍💻 **New Developer**
Read in order:
1. [ARCHITECTURE-INDEX.md](ARCHITECTURE-INDEX.md#im-a-new-developer)
2. [ARCHITECTURE.md](ARCHITECTURE.md)
3. [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md)
4. [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md)

**Goal:** Understand the complete system

### 🔧 **Feature Developer**
Read:
1. [DEVELOPER_REFERENCE.md#adding-features](DEVELOPER_REFERENCE.md#adding-features) - Step-by-step guide
2. [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md) - Understand request flow
3. [SCHEMAS.md](SCHEMAS.md) - Database patterns

**Goal:** Add new features confidently

### 🗄️ **Database Developer**
Read:
1. [SCHEMAS.md](SCHEMAS.md) - All 11 schemas documented
2. [ARCHITECTURE.md#database-schemas](ARCHITECTURE.md#database-schemas) - Schema overview
3. Relevant `contracts/` file for your schema

**Goal:** Understand data models and relationships

### 🔐 **Permission Admin**
Read:
1. [PERMISSIONS.md](PERMISSIONS.md) - Complete RBAC guide
2. [PERMISSIONS.md#adding-new-permissions](PERMISSIONS.md#adding-new-permissions) - How to add

**Goal:** Manage roles and permissions

### 🏗️ **Tech Lead / Architect**
Read all, in this order:
1. [ARCHITECTURE.md](ARCHITECTURE.md) - System overview
2. [ARCHITECTURE-REQUEST-FLOW.md](ARCHITECTURE-REQUEST-FLOW.md) - Request flow
3. [PERMISSIONS.md](PERMISSIONS.md) - RBAC model
4. [SCHEMAS.md](SCHEMAS.md) - Data model
5. [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md) - Patterns

**Goal:** Deep understanding for architectural decisions

## 🔍 Common Tasks

### "I need to..."

| Task | Go To |
|------|-------|
| Understand how the system works | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Add a new feature | [DEVELOPER_REFERENCE.md#adding-features](DEVELOPER_REFERENCE.md#adding-features) |
| Add a new permission | [PERMISSIONS.md#adding-new-permissions](PERMISSIONS.md#adding-new-permissions) |
| Understand a database table | [SCHEMAS.md](SCHEMAS.md) |
| Find a specific route | [ARCHITECTURE-ENTRY-POINTS.md](ARCHITECTURE-ENTRY-POINTS.md) |
| Debug a permission issue | [PERMISSIONS.md#how-permissions-work](PERMISSIONS.md#how-permissions-work) |
| Understand how company context works | [ARCHITECTURE.md#multi-tenancy-architecture](ARCHITECTURE.md#multi-tenancy-architecture) |
| Set up development environment | [DEVELOPER_REFERENCE.md#setup--running](DEVELOPER_REFERENCE.md#setup--running) |
| Troubleshoot an issue | [DEVELOPER_REFERENCE.md#troubleshooting](DEVELOPER_REFERENCE.md#troubleshooting) |
| Find where something is implemented | [ARCHITECTURE-INDEX.md#finding-what-you-need](ARCHITECTURE-INDEX.md#finding-what-you-need) |

## 📋 System Architecture at a Glance

### Tech Stack
- **Frontend:** Vue 3 + TypeScript + Vite + Tailwind + Shadcn
- **Backend:** Laravel 12 + Octane + Inertia.js
- **Database:** PostgreSQL (11 schemas, 100+ tables)
- **Auth:** Laravel Fortify + Spatie Permission

### Key Concepts
- **Multi-Tenancy:** Company-scoped, URL-based (`/{company}/endpoint`)
- **RBAC:** 151 permissions, 6 default roles, company-scoped
- **Modules:** 49+ feature modules across 8+ business domains
- **Database:** 11 schemas with row-level security (RLS)

### Architecture Layers
```
Vue 3 Frontend
    ↓ (Inertia)
Laravel Controllers & FormRequests
    ↓ (Company Context)
Services & Actions
    ↓ (Eloquent ORM)
Models
    ↓ (PDO/Query)
PostgreSQL Database (11 schemas)
```

## 📊 Statistics

- **Lines of Documentation:** 4,000+
- **Documentation Size:** 136 KB
- **Schemas Documented:** 11
- **Tables Documented:** 100+
- **Permissions Listed:** 151
- **Code Examples:** 50+
- **Common Queries:** 4
- **Common Patterns:** 5
- **Architecture Diagrams:** 15+

## 🔗 Related Resources

### In Repository
- **CLAUDE.md** - Development standards and patterns (root)
- **build/app/Constants/Permissions.php** - All permission constants
- **build/routes/web.php** - Route definitions
- **build/app/Models/** - 11 core models
- **build/database/migrations/** - 40+ schema migrations

### External
- [Laravel 12 Docs](https://laravel.com/docs/12)
- [Vue 3 Guide](https://vuejs.org/guide/)
- [Inertia.js](https://inertiajs.com/)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6)
- [Tailwind CSS](https://tailwindcss.com/docs)

## 💡 Tips

1. **First time here?** Start with [ARCHITECTURE-INDEX.md](ARCHITECTURE-INDEX.md)
2. **Looking for something specific?** Use search within the documents
3. **Want to add a feature?** Follow the step-by-step guide in [DEVELOPER_REFERENCE.md](DEVELOPER_REFERENCE.md)
4. **Confused about a database table?** Check [SCHEMAS.md](SCHEMAS.md)
5. **Need to understand permissions?** Read [PERMISSIONS.md](PERMISSIONS.md)

## 📞 Contributing

When adding new documentation:
1. Follow the existing structure and style
2. Include code examples where relevant
3. Add to the appropriate index file
4. Update this README if adding new top-level files

---

**Last Updated:** January 2026  
**Coverage:** 100% of system architecture  
**Status:** Complete and maintained
