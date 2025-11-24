# Quality Validation Guide

**Task**: Validate constitutional compliance across all layers
**Approach**: Automated validation commands

---

## 🎯 VALIDATION LAYERS

### 1. Database Validation
### 2. Model Validation
### 3. Controller Validation
### 4. Frontend Validation
### 5. Integration Validation

---

## 📋 LAYER 1: DATABASE VALIDATION

### Check RLS Policies
```bash
php artisan tinker
> DB::select("
    SELECT schemaname, tablename
    FROM pg_tables
    WHERE schemaname IN ('acct','hsp','crm')
    AND rowsecurity = false
  ");
# Should return empty (all tables have RLS)
```

### Check UUID Primary Keys
```bash
php artisan tinker
> DB::select("
    SELECT table_schema, table_name
    FROM information_schema.columns
    WHERE column_name = 'id'
    AND data_type = 'integer'
    AND table_schema IN ('acct','hsp','crm')
  ");
# Should return empty (no integer PKs)
```

### Check Company ID Columns
```bash
php artisan tinker
> DB::select("
    SELECT table_schema, table_name
    FROM information_schema.tables
    WHERE table_schema IN ('acct','hsp','crm')
    AND table_name NOT IN (
      SELECT table_name
      FROM information_schema.columns
      WHERE column_name = 'company_id'
    )
  ");
# Should return empty (all tenant tables have company_id)
```

---

## 📋 LAYER 2: MODEL VALIDATION

### Check Traits
```bash
# Create script: scripts/validate-models.sh
#!/bin/bash
find modules/*/Models/ -name "*.php" | while read file; do
  if ! grep -q "HasUuids" "$file"; then
    echo "❌ Missing HasUuids: $file"
  fi
  if ! grep -q "BelongsToCompany" "$file"; then
    echo "❌ Missing BelongsToCompany: $file"
  fi
done
```

### Check UUID Configuration
```bash
find modules/*/Models/ -name "*.php" | while read file; do
  if ! grep -q "\$keyType = 'string'" "$file"; then
    echo "❌ Missing keyType: $file"
  fi
  if ! grep -q "\$incrementing = false" "$file"; then
    echo "❌ Missing incrementing: $file"
  fi
done
```

---

## 📋 LAYER 3: CONTROLLER VALIDATION

### Check FormRequest Usage
```bash
# Find controllers with direct Request injection
grep -r "function.*Request \$request" modules/*/Http/Controllers/ \
  | grep -v "FormRequest"
# Should return empty
```

### Check Command Bus Usage
```bash
# Find controllers NOT using Bus::dispatch
find modules/*/Http/Controllers/ -name "*.php" | while read file; do
  if grep -q "public function store\|public function update" "$file"; then
    if ! grep -q "Bus::dispatch" "$file"; then
      echo "❌ Missing Command Bus: $file"
    fi
  fi
done
```

### Check Response Format
```bash
# Find non-standard responses
grep -r "return response()->json" modules/*/Http/Controllers/ \
  | grep -v "success.*data"
# Should return empty or minimal results
```

---

## 📋 LAYER 4: FRONTEND VALIDATION

### Check Composition API
```bash
# Find Options API usage
find modules/*/Resources/js/ -name "*.vue" \
  -exec grep -l "export default {" {} \;
# Should return empty
```

### Check Shadcn/Vue Usage
```bash
# Find HTML elements
find modules/*/Resources/js/ -name "*.vue" \
  -exec grep -l "<input\|<select\|<button[^>]*>" {} \;
# Should return empty (or only in special cases)
```

### Check Inertia Forms
```bash
# Find fetch/axios usage
find modules/*/Resources/js/ -name "*.vue" \
  -exec grep -l "fetch.*POST\|axios.*post" {} \;
# Should return empty
```

---

## 📋 LAYER 5: INTEGRATION VALIDATION

### Test RLS Isolation
```php
// tests/Feature/RlsIsolationTest.php
public function test_rls_prevents_cross_tenant_access()
{
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $customer1 = Customer::factory()->create(['company_id' => $company1->id]);

    $user2 = User::factory()->create();
    $user2->companies()->attach($company2);

    $this->actingAs($user2)
        ->get("/api/customers/{$customer1->id}")
        ->assertStatus(404); // RLS blocks access
}
```

### Test Command Bus Integration
```php
public function test_command_bus_works_end_to_end()
{
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($user)
        ->post('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'test@example.com'
        ])
        ->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'name', 'email'],
            'message'
        ]);

    $this->assertDatabaseHas('acct.customers', [
        'company_id' => $company->id,
        'name' => 'Test Customer'
    ]);
}
```

---

## 🚀 COMPREHENSIVE VALIDATION SCRIPT

```bash
#!/bin/bash
# scripts/comprehensive-validation.sh

echo "🚀 Running Comprehensive Quality Validation..."
VIOLATIONS=0

# 1. Database Layer
echo -e "\n1️⃣  Database Validation..."
if php artisan validate:rls-policies > /dev/null 2>&1; then
    echo "  ✅ RLS Policies: PASS"
else
    echo "  ❌ RLS Policies: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# 2. Model Layer
echo -e "\n2️⃣  Model Validation..."
if bash scripts/validate-models.sh > /dev/null 2>&1; then
    echo "  ✅ Models: PASS"
else
    echo "  ❌ Models: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# 3. Controller Layer
echo -e "\n3️⃣  Controller Validation..."
if bash scripts/validate-controllers.sh > /dev/null 2>&1; then
    echo "  ✅ Controllers: PASS"
else
    echo "  ❌ Controllers: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# 4. Frontend Layer
echo -e "\n4️⃣  Frontend Validation..."
if bash scripts/validate-frontend.sh > /dev/null 2>&1; then
    echo "  ✅ Frontend: PASS"
else
    echo "  ❌ Frontend: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# 5. Integration Tests
echo -e "\n5️⃣  Integration Tests..."
if php artisan test tests/Feature/ConstitutionalComplianceTest.php > /dev/null 2>&1; then
    echo "  ✅ Integration: PASS"
else
    echo "  ❌ Integration: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# 6. Quality Check
echo -e "\n6️⃣  Code Quality..."
if composer quality-check > /dev/null 2>&1; then
    echo "  ✅ Quality: PASS"
else
    echo "  ❌ Quality: FAIL"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# Summary
echo -e "\n📊 VALIDATION SUMMARY"
echo "=============================="

if [ $VIOLATIONS -eq 0 ]; then
    echo "✅ ALL VALIDATIONS PASSED"
    echo "🎉 System is constitutionally compliant!"
    exit 0
else
    echo "❌ FAILURES: $VIOLATIONS"
    echo "⚠️  Please fix violations before deployment"
    exit 1
fi
```

---

## ✅ PRE-DEPLOYMENT CHECKLIST

Run before every deployment:

```bash
# 1. Full validation
bash scripts/comprehensive-validation.sh

# 2. Critical path tests
php artisan test tests/Feature/CriticalPathTest.php

# 3. Performance check
php artisan test tests/Performance/

# 4. Security audit
php artisan test tests/Security/

# 5. Layout validation
php artisan layout:validate --json
```

---

## 📊 VALIDATION REPORT FORMAT

```json
{
  "timestamp": "2025-11-23T10:30:00Z",
  "status": "pass|fail",
  "layers": {
    "database": {
      "rls_policies": "pass",
      "uuid_pks": "pass",
      "company_id": "pass"
    },
    "models": {
      "traits": "pass",
      "uuid_config": "pass",
      "relationships": "pass"
    },
    "controllers": {
      "formrequest": "pass",
      "command_bus": "pass",
      "response_format": "pass"
    },
    "frontend": {
      "composition_api": "pass",
      "shadcn_vue": "pass",
      "inertia_forms": "pass"
    },
    "integration": {
      "rls_isolation": "pass",
      "command_bus": "pass",
      "end_to_end": "pass"
    }
  },
  "violations": [],
  "passed": 15,
  "failed": 0
}
```

---

## 🔧 AUTOMATED FIXES

For common violations, create auto-fix scripts:

```bash
# scripts/auto-fix-models.sh
# Automatically add missing traits to models
find modules/*/Models/ -name "*.php" | while read file; do
  if ! grep -q "HasUuids" "$file"; then
    # Insert traits after class declaration
    sed -i '/class.*extends/a \    use HasUuids, BelongsToCompany, SoftDeletes;' "$file"
  fi
done
```

---

**Reference**: Run these validations in CI/CD pipeline before every merge.
