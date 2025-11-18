# 🚚 HAASIB MIGRATION PLAN: /stack → /build

**Migration Date**: 2025-11-18  
**Source**: `/home/banna/projects/Haasib/stack`  
**Target**: `/home/banna/projects/Haasib/build`  
**Objective**: Clean rebuild with constitutional compliance and zero drift

---

## 📋 MIGRATION OVERVIEW

### Strategy
- **Incremental Copy**: Layer-by-layer migration with testing at each step
- **Constitutional Compliance**: All code follows CLAUDE.md standards
- **Zero Drift**: Use exact templates from CLAUDE.md
- **Rollback Ready**: Each step can be independently reverted

### Success Criteria
- ✅ All migrations run successfully
- ✅ All tests pass
- ✅ Frontend builds without errors
- ✅ Constitutional compliance verified
- ✅ Performance matches or exceeds current

---

## 🏗️ PHASE 1: FOUNDATION SETUP

### Step 1.1: Clean Laravel Installation
```bash
# Create build directory
mkdir -p /home/banna/projects/Haasib/build
cd /home/banna/projects/Haasib/build

# Fresh Laravel install
composer create-project laravel/laravel . --prefer-dist
```

**Testing Checkpoint**: Laravel welcome page loads

### Step 1.2: Core Dependencies
```bash
# Backend dependencies
composer require inertiajs/inertia-laravel
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require predis/predis

# Frontend dependencies  
npm install @inertiajs/vue3 vue@next
npm install primevue@^4.0.0
npm install @primevue/themes
npm install tailwindcss@latest
npm install @vitejs/plugin-vue
```

**Testing Checkpoint**: Dependencies install without conflicts

### Step 1.3: Environment Configuration
```bash
# Copy and modify environment files
cp /home/banna/projects/Haasib/stack/.env.example /home/banna/projects/Haasib/build/.env
cp /home/banna/projects/Haasib/stack/.env /home/banna/projects/Haasib/build/.env.backup

# Update database name in .env
sed -i 's/DB_DATABASE=.*/DB_DATABASE=haasib_build/' /home/banna/projects/Haasib/build/.env
```

**Testing Checkpoint**: Environment loads without errors

---

## 📁 PHASE 2: CONFIGURATION LAYER

### Step 2.1: Laravel Configuration Files
```bash
# Copy core config files (validate each)
cp stack/config/app.php build/config/
cp stack/config/database.php build/config/
cp stack/config/inertia.php build/config/
cp stack/config/permission.php build/config/
cp stack/config/activitylog.php build/config/
```

**Files to Copy:**
- ✅ `config/app.php`
- ✅ `config/database.php`
- ✅ `config/inertia.php` 
- ✅ `config/permission.php`
- ✅ `config/activitylog.php`
- ✅ `config/auth.php`
- ✅ `config/queue.php`
- ✅ `config/cors.php`

**Testing Checkpoint**: `php artisan config:cache` succeeds

### Step 2.2: Composer Dependencies
```bash
# Copy composer.json and validate
cp stack/composer.json build/
cd build && composer install
```

**Testing Checkpoint**: All Composer packages install cleanly

### Step 2.3: Frontend Configuration
```bash
# Copy frontend config files
cp stack/package.json build/
cp stack/vite.config.js build/
cp stack/tailwind.config.js build/
cp stack/postcss.config.js build/

cd build && npm install
```

**Testing Checkpoint**: `npm run dev` starts without errors

---

## 🗄️ PHASE 3: DATABASE LAYER

### Step 3.1: Database Creation
```bash
# Create clean database
PGPASSWORD="AppP@ss123" createdb -h localhost -U postgres haasib_build
```

**Testing Checkpoint**: Database connects successfully

### Step 3.2: Migration Files (Constitutional Order)
```bash
# Copy migrations in dependency order
mkdir -p build/database/migrations

# 1. System migrations first
cp stack/database/migrations/*_create_companies_table.php build/database/migrations/
cp stack/database/migrations/*_create_users_table.php build/database/migrations/
cp stack/database/migrations/*_create_permissions_tables.php build/database/migrations/

# Test system migrations
cd build && php artisan migrate --step

# 2. Module schemas
cp stack/database/migrations/*_create_acct_schema.php build/database/migrations/
cp stack/database/migrations/*_create_hsp_schema.php build/database/migrations/
cp stack/database/migrations/*_create_crm_schema.php build/database/migrations/

# Test schema migrations
php artisan migrate --step

# 3. Module tables (one module at a time)
# Copy acct module migrations
cp stack/database/migrations/*_create_acct_*.php build/database/migrations/
php artisan migrate --step

# Copy hsp module migrations  
cp stack/database/migrations/*_create_hsp_*.php build/database/migrations/
php artisan migrate --step

# Copy crm module migrations
cp stack/database/migrations/*_create_crm_*.php build/database/migrations/
php artisan migrate --step
```

**Testing Checkpoint**: All migrations run successfully, database schema validates

### Step 3.3: Seeders
```bash
# Copy and run seeders
cp -r stack/database/seeders/* build/database/seeders/
cd build && php artisan db:seed
```

**Testing Checkpoint**: Seeders run without errors, basic data exists

---

## 🏛️ PHASE 4: MODEL LAYER

### Step 4.1: Base Models and Traits
```bash
# Copy foundational models first
cp stack/app/Models/User.php build/app/Models/
cp stack/app/Models/Company.php build/app/Models/
cp stack/app/Models/BaseModel.php build/app/Models/

# Copy model concerns/traits
mkdir -p build/app/Models/Concerns
cp stack/app/Models/Concerns/* build/app/Models/Concerns/
```

**Constitutional Validation**:
- ✅ All models use `HasUuids` trait
- ✅ All models use `BelongsToCompany` trait  
- ✅ All models use `SoftDeletes` trait
- ✅ No integer primary keys
- ✅ Proper table schema prefixes

**Testing Checkpoint**: Basic models load without errors

### Step 4.2: Module Models (By Dependency)
```bash
# Copy models module by module
mkdir -p build/app/Models/Acct
cp stack/app/Models/Acct/* build/app/Models/Acct/

mkdir -p build/app/Models/Hsp  
cp stack/app/Models/Hsp/* build/app/Models/Hsp/

mkdir -p build/app/Models/Crm
cp stack/app/Models/Crm/* build/app/Models/Crm/
```

**Testing Checkpoint**: `php artisan tinker` - all models can be instantiated

---

## 🛠️ PHASE 5: SERVICE LAYER

### Step 5.1: Base Services and Context
```bash
# Copy service foundation
mkdir -p build/app/Services
cp stack/app/Services/ServiceContext.php build/app/Services/
cp stack/app/Services/BaseService.php build/app/Services/
```

**Testing Checkpoint**: ServiceContext can be instantiated

### Step 5.1.1: RBAC System Setup
```bash
# Copy RBAC components (CRITICAL FOR FIXING 403s)
cp stack/app/Constants/Permissions.php build/app/Constants/
cp stack/database/seeders/PermissionSeeder.php build/database/seeders/
cp stack/app/Http/Requests/BaseFormRequest.php build/app/Http/Requests/

# Run permission seeder immediately
cd build && php artisan db:seed --class=PermissionSeeder
```

**RBAC Validation Checklist**:
- ✅ All permissions created with standardized naming
- ✅ Role hierarchy established (super_admin → viewer)
- ✅ BaseFormRequest has authorization helpers
- ✅ Permission constants imported correctly

**Testing Checkpoint**: Permission system resolves without 403 errors

### Step 5.2: Command Bus Infrastructure
```bash
# Copy command bus setup
cp stack/app/Providers/CommandBusServiceProvider.php build/app/Providers/
mkdir -p build/app/Actions
cp stack/app/Actions/BaseAction.php build/app/Actions/
```

**Testing Checkpoint**: Command bus resolves properly

### Step 5.3: Module Services
```bash
# Copy module services
mkdir -p build/app/Services/Acct
cp stack/app/Services/Acct/* build/app/Services/Acct/

mkdir -p build/app/Services/Hsp
cp stack/app/Services/Hsp/* build/app/Services/Hsp/

mkdir -p build/app/Services/Crm  
cp stack/app/Services/Crm/* build/app/Services/Crm/
```

**Constitutional Validation**:
- ✅ All services accept ServiceContext
- ✅ No direct `auth()` or `request()` calls
- ✅ Proper dependency injection

**Testing Checkpoint**: Services can be resolved from container

---

## 🎛️ PHASE 6: CONTROLLER LAYER

### Step 6.1: Base Controllers
```bash
# Copy controller foundation
cp stack/app/Http/Controllers/Controller.php build/app/Http/Controllers/
cp stack/app/Http/Controllers/BaseController.php build/app/Http/Controllers/
```

### Step 6.2: Form Requests
```bash
# Copy form requests
mkdir -p build/app/Http/Requests
cp stack/app/Http/Requests/BaseFormRequest.php build/app/Http/Requests/
cp stack/app/Http/Requests/*.php build/app/Http/Requests/
```

**Constitutional Validation**:
- ✅ All FormRequests extend BaseFormRequest
- ✅ All have permission and RLS validation
- ✅ No inline validation in controllers

### Step 6.3: Module Controllers
```bash
# Copy controllers module by module
mkdir -p build/app/Http/Controllers/Acct
cp stack/app/Http/Controllers/Acct/* build/app/Http/Controllers/Acct/

mkdir -p build/app/Http/Controllers/Hsp
cp stack/app/Http/Controllers/Hsp/* build/app/Http/Controllers/Hsp/

mkdir -p build/app/Http/Controllers/Crm
cp stack/app/Http/Controllers/Crm/* build/app/Http/Controllers/Crm/
```

**Constitutional Validation**:
- ✅ All controllers use Command Bus
- ✅ All controllers use FormRequest validation
- ✅ All controllers use ServiceContext
- ✅ No direct service instantiation

**Testing Checkpoint**: Controllers can be resolved, basic endpoints respond

---

## 🛤️ PHASE 7: ROUTING LAYER

### Step 7.1: Route Files
```bash
# Copy route files
cp stack/routes/web.php build/routes/
cp stack/routes/api.php build/routes/
cp stack/routes/console.php build/routes/
```

**Testing Checkpoint**: `php artisan route:list` shows all routes

### Step 7.2: Middleware
```bash
# Copy middleware
mkdir -p build/app/Http/Middleware
cp stack/app/Http/Middleware/* build/app/Http/Middleware/
```

**Testing Checkpoint**: Middleware registers properly

---

## 🎨 PHASE 8: FRONTEND LAYER

### Step 8.1: Base Layout and Components
```bash
# Copy core frontend structure
mkdir -p build/resources/js
cp -r stack/resources/js/app.js build/resources/js/
cp -r stack/resources/js/bootstrap.js build/resources/js/

# Copy layout components first
mkdir -p build/resources/js/Components/Layout
cp stack/resources/js/Components/Layout/* build/resources/js/Components/Layout/
```

### Step 8.2: Strict Layout Components (ZERO DEVIATION)
```bash
# Copy MANDATORY layout components in exact order
cp stack/resources/js/Components/Layout/LayoutShell.vue build/resources/js/Components/Layout/
cp stack/resources/js/Components/UniversalPageHeader.vue build/resources/js/Components/
cp stack/resources/js/Components/PageActions.vue build/resources/js/Components/
cp stack/resources/js/Components/QuickLinks.vue build/resources/js/Components/
cp stack/resources/js/Components/InlineEditable.vue build/resources/js/Components/
```

**STRICT Layout Validation**:
- ✅ All components use Composition API (`<script setup>`)
- ✅ All components use PrimeVue only (NO HTML elements)
- ✅ UniversalPageHeader uses single-row design
- ✅ Proper import order (Vue → Inertia → PrimeVue → App → Utils)
- ✅ Blu-whale theme applied to Sidebar
- ✅ Grid layout follows 5/6 + 1/6 pattern

### Step 8.2.1: Layout Standards Compliance Check
```bash
# Verify layout component structure
cd build && npm run build

# Check for forbidden patterns
grep -r "table>" resources/js/Pages/ || echo "✅ No HTML tables found"
grep -r "button>" resources/js/Pages/ || echo "✅ No HTML buttons found"
grep -r "input>" resources/js/Pages/ || echo "✅ No HTML inputs found"
```

**Layout Compliance Checklist**:
- ✅ Every page uses LayoutShell
- ✅ Every page uses UniversalPageHeader
- ✅ Single-row header design (title + search + actions)
- ✅ Content grid follows standard pattern
- ✅ Permission integration in all components

### Step 8.3: Page Components  
```bash
# Copy pages module by module
mkdir -p build/resources/js/Pages/Acct
cp stack/resources/js/Pages/Acct/* build/resources/js/Pages/Acct/

mkdir -p build/resources/js/Pages/Hsp
cp stack/resources/js/Pages/Hsp/* build/resources/js/Pages/Hsp/

mkdir -p build/resources/js/Pages/Crm
cp stack/resources/js/Pages/Crm/* build/resources/js/Pages/Crm/
```

**Constitutional Validation**:
- ✅ All pages use LayoutShell
- ✅ All pages use UniversalPageHeader
- ✅ All pages follow mandatory structure
- ✅ Inline editing rules followed

**Testing Checkpoint**: `npm run build` succeeds, pages load properly

---

## 🧪 PHASE 9: TESTING LAYER

### Step 9.1: Test Infrastructure
```bash
# Copy test setup
cp stack/phpunit.xml build/
cp stack/tests/TestCase.php build/tests/
mkdir -p build/tests/Feature build/tests/Unit
```

### Step 9.2: Feature Tests
```bash
# Copy tests module by module
cp stack/tests/Feature/Acct/* build/tests/Feature/Acct/ 2>/dev/null || true
cp stack/tests/Feature/Hsp/* build/tests/Feature/Hsp/ 2>/dev/null || true  
cp stack/tests/Feature/Crm/* build/tests/Feature/Crm/ 2>/dev/null || true
```

**Testing Checkpoint**: `php artisan test` passes all copied tests

### Step 9.3: Frontend Tests
```bash
# Copy frontend test setup
cp stack/playwright.config.js build/ 2>/dev/null || true
cp -r stack/tests/Browser build/tests/ 2>/dev/null || true
```

**Testing Checkpoint**: Frontend tests run successfully

---

## ✅ PHASE 10: VALIDATION & FINALIZATION

### Step 10.1: Constitutional Compliance Check
```bash
cd build

# Run all validation scripts
php artisan constitutional:validate
npm run lint
npm run type-check
```

### Step 10.1.1: RBAC & Permission Validation
```bash
# Test permission system
php artisan tinker
>>> User::find(1)->getAllPermissions() // Should return standardized permissions
>>> exit

# Verify no 403 errors on basic routes
php artisan test tests/Feature/PermissionTest.php

# Check FormRequest authorization
grep -r "authorize.*true" app/Http/Requests/ && echo "❌ Found bypassed authorization!" || echo "✅ All requests have proper authorization"
```

### Step 10.1.2: Layout Standards Validation  
```bash
# Check for layout compliance
grep -r "LayoutShell" resources/js/Pages/ | wc -l # Should match page count
grep -r "UniversalPageHeader" resources/js/Pages/ | wc -l # Should match page count

# Verify strict standards
grep -r "<table" resources/js/ && echo "❌ HTML tables found!" || echo "✅ PrimeVue DataTable only"
grep -r "<button" resources/js/ && echo "❌ HTML buttons found!" || echo "✅ PrimeVue Button only"
grep -r "<input" resources/js/ && echo "❌ HTML inputs found!" || echo "✅ PrimeVue inputs only"

# Check permission integration
grep -r "can\." resources/js/Pages/ | wc -l # Should match action count
```

**Enhanced Validation Checklist**:
- ✅ All controllers use Command Bus
- ✅ All models have proper traits  
- ✅ All components use PrimeVue only
- ✅ All inline editing follows rules
- ✅ All responses follow format standards
- ✅ **RBAC system functional (no 403 errors)**
- ✅ **All FormRequests have proper authorization**
- ✅ **Every page follows strict layout standards**
- ✅ **Permission integration complete**
- ✅ **Single-row header design enforced**

### Step 10.2: Performance Validation
```bash
# Build and test performance
npm run build
php artisan optimize
php artisan config:cache
php artisan route:cache
```

**Testing Checkpoint**: Build performance equals or exceeds original

### Step 10.3: Final Integration Tests
```bash
# Run full test suite
php artisan test
npm run test:e2e
```

**Testing Checkpoint**: All tests pass, no regressions

---

## 🚨 ROLLBACK PROCEDURES

### Emergency Rollback
```bash
# Stop build environment  
cd /home/banna/projects/Haasib/build
php artisan down

# Switch back to stack
cd /home/banna/projects/Haasib/stack
php artisan up
```

### Phase-Specific Rollbacks
- **Phase 1-3**: Delete build directory, restart
- **Phase 4-6**: Reset to last working migration checkpoint  
- **Phase 7-8**: Revert specific copied files
- **Phase 9-10**: Fix validation issues, re-test

---

## 📊 SUCCESS METRICS

### Technical Metrics
- ✅ Migration time: < 4 hours total
- ✅ Test coverage: ≥ 80% maintained  
- ✅ Build time: ≤ original stack performance
- ✅ Zero constitutional violations
- ✅ All features functional

### RBAC Success Metrics (NEW)
- ✅ **Zero 403 permission errors**
- ✅ **All FormRequests have proper authorization**
- ✅ **Permission system resolves consistently**
- ✅ **Role hierarchy functions correctly**
- ✅ **Frontend permission checks integrated**

### Layout Standards Success Metrics (NEW)
- ✅ **Every page uses exact layout hierarchy**
- ✅ **Single-row header design enforced**
- ✅ **Zero HTML form elements detected**
- ✅ **PrimeVue components used exclusively**
- ✅ **Blu-whale theme applied consistently**
- ✅ **5/6 + 1/6 grid layout followed**

### Quality Metrics
- ✅ Zero AI drift patterns detected
- ✅ All templates followed exactly
- ✅ Inline editing rules enforced
- ✅ Response formats standardized
- ✅ Import orders consistent
- ✅ **Permission constants used throughout**
- ✅ **Layout compliance 100%**

---

## 🔧 POST-MIGRATION CHECKLIST

### Immediate Tasks
- [ ] Update environment variables
- [ ] Verify database connections
- [ ] Test critical user journeys  
- [ ] Monitor error logs
- [ ] Validate performance metrics

### Documentation Updates
- [ ] Update README.md
- [ ] Document any deviations
- [ ] Record lessons learned
- [ ] Update CLAUDE.md if needed

---

## 📞 SUPPORT CONTACTS

**Migration Lead**: Development Team  
**Database**: DBA Team  
**Frontend**: Frontend Team  
**DevOps**: Infrastructure Team

---

**Migration Status**: ⏸️ Ready to Execute  
**Estimated Duration**: 3-4 hours  
**Risk Level**: Low (incremental with rollbacks)