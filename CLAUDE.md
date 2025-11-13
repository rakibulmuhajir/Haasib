# Haasib Development Instructions & Reference Guide

**Last Updated**: 2025-11-13
**Auto-generated**: Comprehensive instruction dump for AI developers

---

## 🏛️ CONSTITUTIONAL REQUIREMENTS (MANDATORY)

### Core Architecture Principles
You MUST follow these constitutional requirements from `.specify/memory/constitution.md`:

1. **Multi-Schema Domain Separation**
   - Core tables: `public` (system), `auth` (identity)
   - Module-specific schemas: `acct` (accounting), `hsp` (hospitality), `crm` (customer management)
   - RLS (Row Level Security) policies on ALL tenant tables
   - Company-based tenant isolation with `company_id` scoping

2. **Security-First Bookkeeping**
   - All financial mutations require RLS, `company_id` scoping, and audit coverage
   - Positive amount checks and FK constraints in migrations
   - Use `current_setting('app.current_company_id')` for RLS policies

3. **Test & Review Discipline**
   - Database migrations and command-bus handlers ship with regression coverage
   - PRs must describe backward-compatibility impacts
   - Document RLS or audit updates for QA

4. **Observability & Traceability**
   - Use `audit_log()` helper for financial and security events
   - Surface key metrics via monitoring playbooks in `docs/monitoring/`

### Implementation Patterns
- **UUID Primary Keys Only**: Never use integer IDs
- **Command Bus Pattern**: All write operations via `Bus::dispatch()`
- **ServiceContext**: Always inject user/company context explicitly
- **FormRequest Validation**: Never inject `Request` directly in controllers
- **PrimeVue First**: Use PrimeVue v4 components exclusively in frontend
- **Composition API**: All Vue components use `<script setup>` with TypeScript

### Forbidden Patterns (DO NOT USE)
- Direct service calls in controllers (`new Service()`)
- Direct model access in controllers
- Integer primary keys
- Tables in `public` schema (except system tables)
- Missing RLS policies on tenant module schemas (`acct`, `hsp`, `crm`, etc.)
- Non-PrimeVue UI components
- Vue Options API (use Composition API)
- Manual validation in controllers

---

## 📋 TASK-SPECIFIC INSTRUCTION DUMPS

### 🗄️ DATABASE MIGRATIONS

**When Creating New Tables:**
- **Reference File**: `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`
- **Sample Code**: Migration template with UUID, RLS, module schema compliance
- **Key Requirements**: UUID primary keys, company_id, RLS policies, cross-schema FKs
- **Forbidden**: Integer IDs, public schema (except system tables), missing RLS on module schemas

**When Modifying Existing Tables:**
- **Reference File**: `docs/dosdonts/migrations-best-practices.md`
- **Check**: RLS policy updates, audit triggers, constraint changes
- **Validation**: Run `php artisan migrate:fresh --dry-run`

### 🏛️ MODELS

**When Creating New Models:**
- **Reference File**: `AI_PROMPTS/MODEL_REMEDIATION.md`
- **Sample Code**: Complete model with traits, relationships, business logic
- **Required**: `HasUuids`, `BelongsToCompany`, `SoftDeletes`, proper casts
- **Structure**: Properties table, relationships with return types, business methods

**When Updating Models:**
- **Reference File**: `docs/dosdonts/models-best-practices.md`
- **Check**: Trait consistency, fillable arrays, relationship definitions
- **Validation**: Model factory tests still work

### 🎛️ CONTROLLERS

**When Creating New Controllers:**
- **Reference File**: `AI_PROMPTS/CONTROLLER_REMEDIATION.md`
- **Sample Code**: Controller with FormRequest, Command Bus, JSON responses
- **Required**: ServiceContext, Command Bus dispatch, error handling
- **Structure**: Thin controller, FormRequest validation, resource responses

**When Updating Controllers:**
- **Reference File**: `docs/dosdonts/controllers-best-practices.md`
- **Check**: Command Bus usage, response format, validation patterns
- **Validation**: API endpoints still work with existing tests

### 🛠️ SERVICES & COMMANDS

**When Creating New Services:**
- **Reference File**: `docs/dosdonts/services-best-practices.md`
- **Sample Code**: Service with ServiceContext injection, transaction handling
- **Required**: Constructor injection, explicit context, proper error handling
- **Forbidden**: `auth()`, `request()`, direct model access

**When Creating Command Bus Actions:**
- **Reference File**: `docs/dosdonts/command-bus-best-practices.md`
- **Sample Code**: Command action with proper registration and handling
- **Required**: Container resolve, transaction boundaries, audit logging

### 🎨 FRONTEND COMPONENTS

**When Creating New Vue Components:**
- **Reference File**: `AI_PROMPTS/FRONTEND_REMEDIATION.md`
- **Sample Code**: Component with Composition API, PrimeVue, Inertia.js
- **Required**: `<script setup>`, PrimeVue components, error handling
- **Forbidden**: Options API, HTML elements, fetch() calls

**When Creating Forms:**
- **Reference File**: `docs/frontend-architecture.md`
- **Sample Code**: Form with Inertia.js form helper, validation, toast notifications
- **Required**: PrimeVue form components, loading states, error feedback

### 📋 API ENDPOINTS

**When Creating New API Endpoints:**
- **Reference File**: `docs/dosdonts/integration-best-practices.md`
- **Sample Code**: API endpoint with proper routing, middleware, validation
- **Required**: Idempotency-Key header, standard JSON response format
- **Validation**: Test with curl and Postman/Newman

### 🔧 CLI COMMANDS

**When Creating New Artisan Commands:**
- **Reference File**: `docs/dosdonts/cli-best-practices.md`
- **Sample Code**: CLI command with context handling, proper output formatting
- **Required**: ServiceContext handling, CLI-GUI parity, error handling
- **Validation**: Test in both CLI and web contexts

### 🧪 TESTING

**When Creating Tests:**
- **Reference File**: `docs/test-plan-comprehensive.md`
- **Sample Code**: Feature tests, unit tests, browser tests
- **Required**: RLS testing, critical path testing, performance tests
- **Coverage**: Minimum 80% for features, 90% for critical paths

### 🔐 SECURITY

**When Implementing Security Features:**
- **Reference File**: `docs/dosdonts/migration-best-practices.md` (security section)
- **Sample Code**: RLS policies, audit logging, permission checks
- **Required**: Spatie permissions, audit triggers, security headers
- **Validation**: Security scans, penetration testing

### 📊 REPORTING & ANALYTICS

**When Creating Reports:**
- **Reference File**: `docs/test-plan-comprehensive.md` (reporting section)
- **Sample Code**: Financial reports, dashboards, analytics
- **Required**: Proper data aggregation, tenant isolation, performance
- **Validation**: Report accuracy, load testing, data integrity

### 🔄 DATA IMPORT/EXPORT

**When Creating Import Features:**
- **Reference File**: `docs/test-plan-comprehensive.md` (integration section)
- **Sample Code**: CSV/OFX imports, data validation, batch processing
- **Required**: Idempotency protection, error handling, progress feedback
- **Validation**: Large dataset handling, data integrity tests

### 💾 BACKUPS & MAINTENANCE

**When Creating Backup Systems:**
- **Reference File**: `docs/test-plan-comprehensive.md` (disaster recovery)
- **Sample Code**: Automated backups, point-in-time recovery
- **Required**: Data integrity verification, backup testing
- **Validation**: Restore procedures, backup retention policies

---

## 📁 CRITICAL REFERENCE FILES

### Constitutional Documents
- **Constitution**: `.specify/memory/constitution.md`
- **Development Standards**: `DEVELOPMENT_STANDARDS.md`
- **Architecture Guide**: `CONSTITUTION_ARCHITECTURE.md`

### Dos & Don'ts Guides
- **Controllers**: `docs/dosdonts/controllers-best-practices.md`
- **Models**: `docs/dosdonts/models-best-practices.md`
- **Services**: `docs/dosdonts/services-best-practices.md`
- **Migrations**: `docs/dosdonts/migrations-best-practices.md`
- **Integration**: `docs/dosdonts/integration-best-practices.md`
- **CLI**: `docs/dosdonts/cli-best-practices.md`
- **Command Bus**: `docs/dosdonts/command-bus-best-practices.md`
- **Views**: `docs/dosdonts/views-best-practices.md`

### Architecture & Patterns
- **Team Memory**: `docs/TEAM_MEMORY.md`
- **Frontend Architecture**: `docs/frontend-architecture.md`
- **Idempotency**: `docs/idempotency.md`
- **Modules Architecture**: `docs/modules-architecture.md`

### Testing & Quality
- **Test Plan**: `docs/test-plan-comprehensive.md`
- **Quality Gates**: `QUALITY_GATES_AUTOMATION.md`
- **Implementation Plan**: `IMPLEMENTATION_PLAN.md`

### AI Development
- **Master Remediation**: `AI_PROMPTS/MASTER_REMEDIATION_PROMPT.md`
- **Database Schema**: `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md`
- **Controller**: `AI_PROMPTS/CONTROLLER_REMEDIATION.md`
- **Model**: `AI_PROMPTS/MODEL_REMEDIATION.md`
- **Frontend**: `AI_PROMPTS/FRONTEND_REMEDIATION.md`
- **Systematic Replacement**: `AI_PROMPTS/SYSTEMATIC_REPLACEMENT_GUIDE.md`
- **Quality Validation**: `AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md`

---

## 🚀 PROJECT STRUCTURE

### Working Directories
- **Active Laravel**: `/stack` (main working directory)
- **Feature Specs**: `/specs/` (feature specifications)
- **Documentation**: `/docs/` (project documentation)
- **AI Prompts**: `/AI_PROMPTS/` (AI development prompts)
- **Templates**: `.specify/templates/` (consistency templates)
- **Old Directory**: `/app` (deprecated, use `/stack` instead)

### Technology Stack
- **Backend**: PHP 8.2+, Laravel 12, PostgreSQL 16
- **Frontend**: Vue 3, Inertia.js v2, PrimeVue v4, Tailwind CSS
- **Authentication**: Laravel Sanctum, Spatie Laravel Permission
- **Testing**: PestPHP, Playwright
- **Queue**: Redis + Laravel Horizon

---

## ✅ QUALITY GATES

### Pre-Commit Checks
```bash
cd stack && composer quality-check
./.husky/pre-commit
php artisan test tests/Feature/CriticalPathTest.php
```

### Database Validation
```bash
php artisan migrations:validate
php artisan schema:check-integrity
```

### Frontend Validation
```bash
npm run build
npm run test:e2e
```

---

## 🎯 TASK EXECUTION CHECKLIST

### For Any Development Task:
1. **Check Constitutional Requirements** - Verify compliance
2. **Reference Relevant Files** - Use sample code patterns
3. **Follow Implementation Patterns** - Apply established approaches
4. **Run Quality Gates** - Validate before commit
5. **Test Integration** - Ensure no regressions
6. **Update Documentation** - Keep references current

### For Problem Solving:
1. **Read Dos & Don'ts** - Check for specific anti-patterns
2. **Consult Team Memory** - Reference established patterns
3. **Review AI Prompts** - Use remediation guides
4. **Run Validation Scripts** - Automated compliance checks

---

**Remember**: Every AI session should reference these instructions and sample code files to ensure consistent, constitutional-compliant development across your team.