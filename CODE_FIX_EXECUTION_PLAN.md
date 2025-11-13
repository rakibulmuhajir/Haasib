# Code Fix Execution Plan

**Goal**: Systematically fix all existing code to meet constitutional requirements
**Start Date**: 2025-11-13

---

## 🚀 **PHASE 0: PREPARATION (Do This First)**

### 1. Create Working Environment
```bash
# Create a backup branch for safety
git checkout -b code-fix-$(date +%Y%m%d_%H%M%S)
git add .
git commit -m "Backup before systematic code fixes"

# Run baseline tests to know current state
cd stack && composer quality-check > baseline-report.txt 2>&1
```

### 2. Setup Tracking
```bash
# Create progress tracking
echo "# Code Fix Progress

## Issues Found
- [ ] Database Schema Issues
- [ ] Model Issues
- [ ] Controller Issues
- [ ] Frontend Issues
- [ ] Service/Command Bus Issues

## Fixed
- [ ]

## Current Status
- [ ] Phase 1: Database
- [ ] Phase 2: Models
- [ ] Phase 3: Controllers
- [ ] Phase 4: Services
- [ ] Phase 5: Frontend
" > CODE_FIX_PROGRESS.md
```

---

## 📊 **PHASE 1: DATABASE SCHEMA FIXES (Most Critical)**

### Step 1: Identify All Non-Compliant Migrations
```bash
# Run the AI migration analysis prompt
cd /home/banna/projects/Haasib

# Use the AI prompts
echo "Running AI analysis on migrations..."
cat > /tmp/migration_fix_prompt.txt << 'EOF'
Based on AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md, analyze and fix ALL migrations in stack/database/migrations/ to ensure:

1. UUID primary keys only
2. Core schemas: public (system), auth (identity)
3. Module schemas: acct (accounting)
4. RLS policies on all tenant tables
5. company_id on all tenant tables
6. Cross-schema foreign keys

For each migration that needs fixing:
- Show the current problematic code
- Show the corrected code
- Explain what was changed and why

Run through ALL migrations systematically.
EOF

# Send this to your AI developers with the migration files
```

### Step 2: Fix Migrations Systematically
```bash
# Find all migrations with issues
find stack/database/migrations/ -name "*.php" -exec grep -l "\$table->id()" {} \;
find stack/database/migrations/ -name "*.php" -exec grep -L "Schema::create(" {} \; | grep -v "auth\|acct\|public"

# For each migration, use the AI prompt to fix it
# Example:
# "Please fix this migration using the pattern from AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md: [file_path]"
```

### Step 3: Validate Fixed Migrations
```bash
cd stack

# Test that migrations work
php artisan migrate:fresh --dry-run

# Check for RLS policies
php artisan tinker <<EOF
\$policies = DB::select("SELECT schemaname, tablename FROM pg_policies WHERE tablename LIKE '%_%'");
foreach (\$policies as \$policy) {
    echo "Policy: {\$policy->schemaname}.{\$policy->tablename}\n";
}
EOF

# Validate table structure
php artisan schema:check-integrity
```

---

## 🏛️ **PHASE 2: MODEL FIXES**

### Step 1: Fix All Models
```bash
# Find models missing required traits
find stack/app/Models/ -name "*.php" -exec grep -L "HasUuids\|BelongsToCompany" {} \;

# For each model found, use: "Based on AI_PROMPTS/MODEL_REMEDIATION.md, fix this model: [file_path]"
```

### Step 2: Critical Models to Fix First
```bash
# Priority models based on your system
critical_models=(
    "stack/app/Models/User.php"
    "stack/app/Models/Auth/Company.php"
    "stack/app/Models/Acct/Customer.php"
    "stack/app/Models/Acct/Invoice.php"
    "stack/app/Models/Acct/Payment.php"
)

for model in "${critical_models[@]}"; do
    echo "Fixing: $model"
    # Use AI prompt: "Based on AI_PROMPTS/MODEL_REMEDIATION.md, fix this model: $model"
done
```

### Step 3: Validate Models
```bash
# Test that models work
php artisan tinker <<EOF
try {
    \$user = new \App\Models\User();
    echo "User model loads successfully\n";
} catch (Exception \$e) {
    echo "User model error: " . \$e->getMessage() . "\n";
}

try {
    \$customer = new \App\Models\Acct\Customer();
    echo "Customer model loads successfully\n";
} catch (Exception \$e) {
    echo "Customer model error: " . \$e->getMessage() . "\n";
}
EOF
```

---

## 🎛️ **PHASE 3: CONTROLLER FIXES**

### Step 1: Find Controllers with Issues
```bash
# Controllers with direct service calls
find stack/app/Http/Controllers/ -name "*.php" -exec grep -l "new.*Service()" {} \;

# Controllers with direct Request injection
find stack/app/Http/Controllers/ -name "*.php" -exec grep -L "Request \$request" {} \;

# Controllers with non-standard responses
find stack/app/Http/Controllers/ -name "*.php" -exec grep -L "return response()->json(" {} \;
```

### Step 2: Fix Controllers Systematically
```bash
# Priority controllers
critical_controllers=(
    "stack/app/Http/Controllers/Auth/AuthController.php"
    "stack/app/Http/Controllers/UserController.php"
    "stack/app/Http/Controllers/Api/CustomerController.php"
    "stack/app/Http/Controllers/Api/InvoiceController.php"
    "stack/app/Http/Controllers/Api/PaymentController.php"
)

for controller in "${critical_controllers[@]}"; do
    if [ -f "$controller" ]; then
        echo "Fixing: $controller"
        # Use AI prompt: "Based on AI_PROMPTS/CONTROLLER_REMEDIATION.md, fix this controller: $controller"
    fi
done
```

### Step 3: Create Missing FormRequest Classes
```bash
# Check what FormRequest classes are needed
find stack/app/Http/Controllers/ -name "*.php" -exec grep -l "Request \$request" {} \;

# Create FormRequest classes for each controller method that needs them
mkdir -p stack/app/Http/Requests/{Customers,Invoices,Payments,Users}
```

---

## 🛠️ **PHASE 4: SERVICES & COMMAND BUS**

### Step 1: Fix Service Classes
```bash
# Find services with auth() calls
find stack/app/Services/ -name "*.php" -exec grep -l "auth()\|request()\|session()" {} \;

# For each service, use: "Based on docs/dosdonts/services-best-practices.md, fix this service: [file_path]"
```

### Step 2: Set Up Command Bus
```bash
# Create command bus configuration if missing
if [ ! -f "stack/config/command-bus.php" ]; then
    echo "Creating command bus configuration..."
    # Use AI to create this based on your existing service patterns
fi

# Register existing services as command bus actions
```

---

## 🎨 **PHASE 5: FRONTEND COMPONENTS**

### Step 1: Identify Components to Fix
```bash
# Components using Options API
find stack/resources/js/ -name "*.vue" -exec grep -l "export default {" {} \;

# Components with non-PrimeVue elements
find stack/resources/js/ -name "*.vue" -exec grep -l "<input\|<select\|<button" {} \;

# Components using fetch instead of Inertia
find stack/resources/js/ -name "*.vue" -exec grep -l "fetch.*POST\|axios.*POST" {} \;
```

### Step 2: Fix Components Systematically
```bash
# Priority components
critical_components=(
    "stack/resources/js/Pages/Auth/Login.vue"
    "stack/resources/js/Pages/Auth/Register.vue"
    "stack/resources/js/Pages/Dashboard.vue"
    "stack/resources/js/Pages/Customers/Index.vue"
    "stack/resources/js/Pages/Invoices/Index.vue"
    "stack/resources/js/Components/Forms/CustomerForm.vue"
    "stack/resources/js/Components/Forms/InvoiceForm.vue"
)

for component in "${critical_components[@]}"; do
    if [ -f "$component" ]; then
        echo "Fixing: $component"
        # Use AI prompt: "Based on AI_PROMPTS/FRONTEND_REMEDIATION.md, fix this component: $component"
    fi
done
```

---

## ✅ **PHASE 6: VALIDATION & TESTING**

### Step 1: Run Comprehensive Tests
```bash
# Run all AI validation prompts
echo "Running validation prompts..."

# Database validation
php artisan validate:rls-policies
php artisan validate:models

# Quality checks
cd stack && composer quality-check

# Critical path tests
php artisan test tests/Feature/CriticalPathTest.php
php artisan test tests/Feature/ConstitutionalIntegrationTest.php
```

### Step 2: Frontend Validation
```bash
cd stack

# Build test
npm run build

# E2E tests
npm run test:e2e

# Performance check (if available)
npm run test:performance
```

### Step 3: Integration Testing
```bash
# Test complete workflows
echo "Testing user registration → company creation → customer management workflow..."

# API endpoint testing
curl -X POST http://localhost/api/customers \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name":"Test Customer","email":"test@example.com"}'
```

---

## 📋 **PROGRESS TRACKING**

### Daily Update Script
```bash
#!/bin/bash
# update-progress.sh

echo "=== CODE FIX PROGRESS UPDATE ===" >> CODE_FIX_PROGRESS.md
echo "**Date**: $(date)" >> CODE_FIX_PROGRESS.md
echo "" >> CODE_FIX_PROGRESS.md

echo "## Status Updates" >> CODE_FIX_PROGRESS.md

# Check migrations fixed
migrations_fixed=$(find stack/database/migrations/ -name "*.php" | grep -L "uuid('id')->primary()" | wc -l)
total_migrations=$(find stack/database/migrations/ -name "*.php" | wc -l)
echo "- **Migrations**: $migrations_fixed/$total_migrations UUID compliant" >> CODE_FIX_PROGRESS.md

# Check models fixed
models_fixed=$(find stack/app/Models/ -name "*.php" | xargs grep -l "HasUuids\|BelongsToCompany" | wc -l)
total_models=$(find stack/app/Models/ -name "*.php" | wc -l)
echo "- **Models**: $models_fixed/$total_models traits compliant" >> CODE_FIX_PROGRESS.md

# Check controllers fixed
controllers_fixed=$(find stack/app/Http/Controllers/ -name "*..php" | xargs grep -L "FormRequest\|Bus::dispatch" | wc -l)
total_controllers=$(find stack/app/Http/Controllers/ -name "*..php" | wc -l)
echo "- **Controllers**: $controllers_fixed/$total_controllers constitutional" >> CODE_FIX_PROGRESS.md

# Check components fixed
components_fixed=$(find stack/resources/js/ -name "*.vue" | xargs grep -l "script setup" | wc -l)
total_components=$(find stack/resources/js/ -name "*..vue" | wc -l)
echo "- **Frontend**: $components_fixed/$total_components Composition API" >> CODE_FIX_PROGRESS.md

echo "- **Overall Quality**: $((($migrations_fixed + $models_fixed + $controllers_fixed + $components_fixed) / ($total_migrations + $total_models + $total_controllers + $total_components)) * 100))% compliant" >> CODE_FIX_PROGRESS.md
```

### Run Daily Update
```bash
chmod +x update-progress.sh
./update-progress.sh
```

---

## 🚨 **AI DEVELOPERS WORKFLOW**

### For Each AI Developer:
1. **Get Current Task**: Check CODE_FIX_PROGRESS.md for next priority
2. **Reference Instructions**: Use `CLAUDE.md` for the specific task type
3. **Use AI Prompts**: Reference the appropriate remediation prompt
4. **Validate**: Run quality gates before committing
5. **Update Progress**: Mark items as complete

### AI Developer Commands:
```bash
# For database migrations:
"Please fix this migration using AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md: [file_path]"

# For models:
"Please fix this model using AI_PROMPTS/MODEL_REMEDIATION.md: [file_path]"

# For controllers:
"Please fix this controller using AI_PROMPTS/CONTROLLER_REMEDIATION.md: [file_path]"

# For frontend:
"Please fix this component using AI_PROMPTS/FRONTEND_REMEDIATION.md: [file_path]"

# For validation:
"Please validate this fix using AI_PROMPTS/QUALITY_VALIDATION_PROMPT.md: [context]"
```

---

## 📅 **SUCCESS CRITERIA**

### Phase Completion:
- [ ] **Database**: All migrations use UUID + RLS + correct schemas
- [ ] **Models**: All models use traits + proper relationships
- [ ] **Controllers**: All use FormRequest + Command Bus
- [ ] **Services**: All use ServiceContext + no direct auth calls
- [ ] **Frontend**: All components use PrimeVue + Composition API

### Final Validation:
- [ ] All quality gates pass
- [ ] Critical path tests pass
- [ ] No constitutional violations
- [ ] Integration tests pass
- [ ] Performance tests pass

### Final Step:
```bash
# Final comprehensive validation
cd stack && composer quality-check
php artisan test tests/Feature/ConstitutionalIntegrationTest.php

# Create final report
echo "## FINAL VALIDATION COMPLETE" >> CODE_FIX_PROGRESS.md
echo "**Status**: ✅ ALL CONSTITUTIONAL REQUIREMENTS MET" >> CODE_FIX_PROGRESS.md

# Commit changes
git add .
git commit -m "Systematic code fixes: constitutional compliance achieved 🏛️"
```

---

**Ready to start?** Begin with Phase 1 (Database) as it's the foundation for everything else! 🚀