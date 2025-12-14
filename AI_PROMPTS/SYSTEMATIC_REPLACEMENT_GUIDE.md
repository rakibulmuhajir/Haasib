# Systematic Code Replacement Guide

## Task: Complete Constitutional Compliance Transformation

You are an **Architectural Transformation Expert** tasked with systematically replacing all non-compliant code patterns with established constitutional standards.

## OVERALL TRANSFORMATION STRATEGY

### **Phase-Based Approach**
1. **Database First** - Fix schemas, migrations, and models
2. **Backend Second** - Fix controllers, services, and API layer
3. **Frontend Third** - Fix Vue components and UI patterns
4. **Integration Fourth** - Test complete workflows
5. **Validation Fifth** - Run comprehensive quality checks

---

## **PHASE 1: DATABASE LAYER TRANSFORMATION**

### **Migration Replacement Patterns**

#### **Pattern 1: Integer to UUID Primary Keys**
```php
// FIND ALL MIGRATIONS WITH INTEGER PRIMARY KEYS
grep -r "\$table->id()" app/Database/Migrations/

// REPLACE PATTERN
// BEFORE
Schema::create('table_name', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});

// AFTER
Schema::create('schema.table_name', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id');
    $table->string('name');
    $table->timestamps();
    $table->softDeletes();
});
```

#### **Pattern 2: Schema Assignment**
```php
// FIND ALL TABLES IN PUBLIC SCHEMA
grep -r "Schema::create('" app/Database/Migrations/ | grep -v "auth\|acct\|ledger\|ops"

// REPLACE PATTERN
// BEFORE
Schema::create('customers', function (Blueprint $table) {

// AFTER
Schema::create('acct.customers', function (Blueprint $table) {
```

#### **Pattern 3: RLS Policy Addition**
```php
// FIND ALL MIGRATIONS WITHOUT RLS
for migration in $(find app/Database/Migrations/ -name "*.php"); do
    if ! grep -q "ENABLE ROW LEVEL SECURITY" "$migration"; then
        echo "RLS Missing: $migration"
    fi
done

// ADD RLS TO ALL MIGRATIONS
DB::statement('ALTER TABLE schema.table_name ENABLE ROW LEVEL SECURITY');
DB::statement('ALTER TABLE schema.table_name FORCE ROW LEVEL SECURITY');
```

#### **Pattern 4: Company Context Addition**
```php
// FIND ALL TENANT TABLES WITHOUT company_id
grep -r "Schema::create" app/Database/Migrations/ | grep -E "(auth|acct|ledger|ops)" | while read line; do
    table=$(echo $line | sed -n 's/.*create(\(.*\)).*/\1/p')
    if ! grep -r "company_id" "app/Database/Migrations/" | grep -q "$table"; then
        echo "Missing company_id: $table"
    fi
done
```

### **Model Replacement Patterns**

#### **Pattern 1: Trait Addition**
```php
// FIND ALL MODELS MISSING TRAITS
find app/Models/ -name "*.php" | while read model; do
    if ! grep -q "HasUuids\|BelongsToCompany\|SoftDeletes" "$model"; then
        echo "Missing traits: $model"
    fi
done

// REPLACE PATTERN
// BEFORE
class Customer extends Model
{
    protected $fillable = ['name'];
}

// AFTER
use App\Traits\HasUuids, BelongsToCompany, SoftDeletes, AuditLog;

class Customer extends BaseModel
{
    use HasUuids, BelongsToCompany, SoftDeletes, AuditLog;

    protected $table = 'acct.customers';
    protected $fillable = ['company_id', 'name'];
    protected $casts = ['company_id' => 'string'];

    protected $keyType = 'string';
    public $incrementing = false;
}
```

---

## **PHASE 2: BACKEND LAYER TRANSFORMATION**

### **Controller Replacement Patterns**

#### **Pattern 1: FormRequest Injection**
```php
// FIND ALL CONTROLLERS WITH DIRECT REQUEST INJECTION
grep -r "Request \$request" app/Http/Controllers/

// REPLACE PATTERN
// BEFORE
public function store(Request $request)
{
    $validated = $request->validate([...]);
}

// AFTER
public function store(StoreEntityRequest $request)
{
    // Validation handled by FormRequest
}
```

#### **Pattern 2: Command Bus Implementation**
```php
// FIND ALL CONTROLLERS WITH DIRECT SERVICE CALLS
grep -r "new.*Service()" app/Http/Controllers/

// REPLACE PATTERN
// BEFORE
$result = new EntityService()->process($data);

// AFTER
$context = ServiceContextHelper::fromRequest($request);
$result = Bus::dispatch('entity.action', $data, $context);
```

#### **Pattern 3: Standard Response Format**
```php
// FIND ALL NON-STANDARD RESPONSES
grep -r "return response()->json(" app/Http/Controllers/ | grep -v "success.*data.*message"

// REPLACE PATTERN
// BEFORE
return response()->json($entity);

// AFTER
return response()->json([
    'success' => true,
    'data' => new EntityResource($entity),
    'message' => 'Operation completed successfully'
]);
```

### **Service Layer Transformation**

#### **Pattern 1: ServiceContext Implementation**
```php
// FIND ALL SERVICES WITH auth() CALLS
grep -r "auth()" app/Services/

// REPLACE PATTERN
// BEFORE
class Service {
    public function process($data) {
        $user = auth()->user(); // ❌ Direct auth call
    }
}

// AFTER
class Service {
    public function process($data, ServiceContext $context) {
        $user = $context->getUser(); // ✅ Context injection
    }
}
```

---

## **PHASE 3: FRONTEND LAYER TRANSFORMATION**

### **Component Replacement Patterns**

#### **Pattern 1: PrimeVue Component Migration**
```bash
# FIND ALL NON-PRIMEVUE COMPONENTS
find resources/js/ -name "*.vue" -exec grep -l "<input\|<select\|<button" {} \;

# REPLACE PATTERNS
# BEFORE
<input v-model="form.name">

# AFTER
<InputText v-model="form.name" />
```

#### **Pattern 2: Composition API Migration**
```bash
# FIND ALL OPTIONS API COMPONENTS
find resources/js/ -name "*.vue" -exec grep -l "export default {" {} \;

# REPLACE PATTERN
# BEFORE
export default {
    data() {
        return { form: {} }
    },
    methods: {
        save() {}
    }
}

// AFTER
<script setup>
import { ref } from 'vue'
const form = ref({})
const save = () => {}
</script>
```

#### **Pattern 3: Inertia Form Migration**
```bash
# FIND ALL FETCH-BASED FORM SUBMISSIONS
find resources/js/ -name "*.vue" -exec grep -l "fetch.*POST\|axios.*POST" {} \;

# REPLACE PATTERN
// BEFORE
const response = await fetch('/api/entity', {
    method: 'POST',
    body: JSON.stringify(form.value)
});

// AFTER
const form = useForm(data)
form.post('/entity', {
    onSuccess: () => {},
    onError: (errors) => {}
});
```

---

## **PHASE 4: TESTING AND VALIDATION**

### **Automated Validation Commands**

```bash
#!/bin/bash
# scripts/validate-transformation.sh

echo "🔍 Validating Constitutional Compliance..."

# 1. Check Database Compliance
echo "  1. Database Schema Compliance..."
php artisan migrations:validate

# 2. Check Model Compliance
echo "  2. Model Constitutional Compliance..."
php artisan tinker <<EOF
$models = glob('app/Models/**/*.php');
foreach ($models as $model) {
    $content = file_get_contents($model);
    if (!str_contains($content, 'HasUuids') && !str_contains($content, 'BaseModel')) {
        echo "Missing traits: $model\n";
    }
}
EOF

# 3. Check Controller Compliance
echo "  3. Controller Compliance..."
php artisan tinker <<EOF
$controllers = glob('app/Http/Controllers/**/*.php');
foreach ($controllers as $controller) {
    $content = file_get_contents($controller);
    if (str_contains($content, 'Request $request') && !str_contains($content, 'FormRequest')) {
        echo "Direct Request injection: $controller\n";
    }
    if (str_contains($content, 'new.*Service()')) {
        echo "Direct service call: $controller\n";
    }
}
EOF

# 4. Check Frontend Compliance
echo "  4. Frontend PrimeVue Compliance..."
find resources/js/ -name "*.vue" -exec grep -l "<input\|<select\|<button" {} \;

# 5. Run Critical Path Tests
echo "  5. Critical Path Tests..."
php artisan test tests/Feature/CriticalPathTest.php

# 6. Quality Check
echo "  6. Overall Quality Check..."
composer quality-check

echo "✅ Validation Complete!"
```

---

## **PHASE 5: INTEGRATION TESTING**

### **Complete Workflow Validation**

```php
// tests/Feature/ConstitutionalComplianceTest.php
class ConstitutionalComplianceTest extends TestCase
{
    /** @test */
    public function complete_customer_workflow_follows_constitution()
    {
        // 1. Test customer creation follows constitutional patterns
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'admin']);

        $response = $this->actingAs($user)->post('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'status' => 'active'
        ], [
            'Idempotency-Key' => Str::uuid()
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['success', 'data', 'message']);

        // 2. Verify database follows constitutional patterns
        $this->assertDatabaseHas('acct.customers', [
            'company_id' => $company->id,
            'name' => 'Test Customer'
        ]);

        // 3. Verify RLS is working
        $this->actingAs(User::factory()->create())
             ->get("/api/customers/{$response->json('data.id')}")
             ->assertStatus(404); // RLS blocks cross-tenant access

        // 4. Test frontend can handle the data
        $this->get('/customers')
             ->assertOk();
    }

    /** @test */
    public function all_controllers_use_command_bus()
    {
        $controllers = glob(app_path('Http/Controllers/**/*.php'));

        foreach ($controllers as $controller) {
            $content = file_get_contents($controller);

            // Should not have direct service calls
            $this->assertStringNotContainsString(
                'new.*Service()',
                $content,
                "Controller {$controller} should not call services directly"
            );

            // Should use FormRequest for validation
            if (str_contains($content, 'Request $request')) {
                $this->assertStringContainsString(
                    'FormRequest',
                    $content,
                    "Controller {$controller} should use FormRequest classes"
                );
            }
        }
    }

    /** @test */
    public function all_models_follow_uuid_patterns()
    {
        $models = glob(app_path('Models/**/*.php'));

        foreach ($models as $model) {
            if (str_contains($model, 'BaseModel.php')) {
                continue; // Skip base model
            }

            $content = file_get_contents($model);

            // Should use UUID traits
            $this->assertStringContainsString(
                'HasUuids',
                $content,
                "Model {$model} should use HasUuids trait"
            );

            // Should have proper UUID configuration
            $this->assertStringContainsString(
                '$keyType = \'string\'',
                $content,
                "Model {$model} should have string key type"
            );

            $this->assertStringContainsString(
                '$incrementing = false',
                $content,
                "Model {$model} should disable auto-increment"
            );
        }
    }

    /** @test */
    public function all_migrations_have_rls_policies()
    {
        // This would require custom migration inspection
        // For now, we'll test that RLS is working at runtime
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        // Different user should not see the customer
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
             ->get("/api/customers/{$customer->id}")
             ->assertStatus(404);

        // Company user should see the customer
        $user->companies()->attach($company, ['role' => 'admin']);
        $this->actingAs($user)
             ->get("/api/customers/{$customer->id}")
             ->assertOk();
    }
}
```

---

## **VALIDATION CHECKLIST**

### **Database Layer ✅**
- [ ] All tables use correct schema (auth/acct/ledger/ops)
- [ ] All tables have UUID primary keys
- [ ] All tenant tables have company_id
- [ ] All tenant tables have RLS policies
- [ ] All migrations have proper rollback
- [ ] All foreign keys reference correct schemas

### **Model Layer ✅**
- [ ] All models extend BaseModel or similar
- [ ] All models use HasUuids, BelongsToCompany, SoftDeletes
- [ ] All models have proper UUID configuration
- [ ] All relationships have proper return types
- [ ] All models have business logic methods
- [ ] All models follow naming conventions

### **Controller Layer ✅**
- [ ] All controllers use FormRequest validation
- [ ] All write operations use Command Bus
- [ ] All responses follow standard format
- [ ] All controllers have proper error handling
- [ ] All controllers use ServiceContext
- [ ] No direct service calls or model access

### **Frontend Layer ✅**
- [ ] All components use PrimeVue components
- [ ] All components use Composition API
- [ ] All forms use Inertia.js form helpers
- [ ] All components have proper error handling
- [ ] All components are responsive
- [ ] All components have accessibility features

### **Integration Layer ✅**
- [ ] Complete end-to-end workflows work
- [ ] RLS policies prevent cross-tenant access
- [ ] Command Bus integrates with frontend
- [ ] Error handling works across layers
- [ ] Performance is acceptable
- [ ] Security measures are effective

---

## **EXECUTION PLAN**

### **Week 1: Database Layer**
1. Fix all migrations to use UUID and RLS
2. Update all models to use proper traits
3. Test database integrity and RLS compliance

### **Week 2: Backend Layer**
1. Update all controllers to use Command Bus
2. Create all necessary FormRequest classes
3. Implement all ServiceContext patterns

### **Week 3: Frontend Layer**
1. Convert all components to PrimeVue
2. Migrate all components to Composition API
3. Update all forms to use Inertia.js

### **Week 4: Integration and Testing**
1. Test complete workflows
2. Run comprehensive quality checks
3. Deploy to staging for final validation

---

This systematic approach ensures complete constitutional compliance while minimizing regressions and maintaining development velocity.