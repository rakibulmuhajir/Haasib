# Quality Validation and Testing Prompt

## Task: Validate All Remediated Code Against Constitutional Requirements

You are a **Quality Assurance Expert** responsible for validating that all remediated code meets constitutional requirements and maintains system integrity.

## COMPREHENSIVE VALIDATION FRAMEWORK

### **Layer 1: Database Validation**

#### **Schema Compliance Check**
```bash
#!/bin/bash
# scripts/validate-database-schema.sh

echo "🔍 Validating Database Schema Constitutional Compliance..."

# Check 1: All tables use correct schemas
echo "  Checking schema placement..."
INVALID_SCHEMAS=$(php artisan tinker <<EOF
$tables = DB::select("SELECT table_schema, table_name FROM information_schema.tables WHERE table_schema NOT IN ('auth', 'acct', 'ledger', 'ops', 'public') AND table_schema NOT LIKE 'information_schema%' AND table_schema NOT LIKE 'pg_%'");
foreach ($tables as $table) {
    if ($table->table_schema !== 'public' || !in_array($table->table_name, ['migrations', 'failed_jobs', 'cache'])) {
        echo "Invalid schema: {$table->table_schema}.{$table->table_name}\n";
    }
}
EOF
)

if [ ! -z "$INVALID_SCHEMAS" ]; then
    echo "❌ Tables found in invalid schemas:"
    echo "$INVALID_SCHEMAS"
    exit 1
fi

# Check 2: All tenant tables have UUID primary keys
echo "  Checking UUID primary keys..."
INVALID_PK=$(php artisan tinker <<EOF
$tenantTables = DB::select("SELECT table_schema, table_name FROM information_schema.columns WHERE column_name = 'id' AND data_type = 'integer' AND table_schema IN ('auth', 'acct', 'ledger', 'ops')");
foreach ($tenantTables as $table) {
    echo "Integer primary key found: {$table->table_schema}.{$table->table_name}.{$table->column_name}\n";
}
EOF
)

if [ ! -z "$INVALID_PK" ]; then
    echo "❌ Integer primary keys found:"
    echo "$INVALID_PK"
    exit 1
fi

# Check 3: All tenant tables have company_id
echo "  Checking company_id columns..."
MISSING_COMPANY_ID=$(php artisan tinker <<EOF
$tenantTables = DB::select("SELECT table_schema, table_name FROM information_schema.tables WHERE table_schema IN ('auth', 'acct', 'ledger', 'ops') AND table_name NOT LIKE '%_migrations'");
foreach ($tenantTables as $table) {
    $hasCompanyId = DB::select("SELECT COUNT(*) as count FROM information_schema.columns WHERE table_schema = '{$table->table_schema}' AND table_name = '{$table->table_name}' AND column_name = 'company_id'");
    if ($hasCompanyId[0]->count == 0) {
        echo "Missing company_id: {$table->table_schema}.{$table->table_name}\n";
    }
}
EOF
)

if [ ! -z "$MISSING_COMPANY_ID" ]; then
    echo "❌ Missing company_id columns:"
    echo "$MISSING_COMPANY_ID"
    exit 1
fi

echo "✅ Database schema validation passed!"
```

#### **RLS Policy Validation**
```php
// app/Console/Commands/ValidateRLSPolicies.php

class ValidateRLSPolicies extends Command
{
    protected $signature = 'validate:rls-policies';
    protected $description = 'Validate RLS policies on all tenant tables';

    public function handle(): int
    {
        $violations = [];

        // Check all tenant tables have RLS enabled
        $tenantTables = DB::select("
            SELECT table_schema, table_name
            FROM information_schema.tables
            WHERE table_schema IN ('auth', 'acct', 'ledger', 'ops')
            AND table_name NOT LIKE '%_migrations'
        ");

        foreach ($tenantTables as $table) {
            $tableName = "{$table->table_schema}.{$table->table_name}";

            // Check if RLS is enabled
            $rlsEnabled = DB::selectOne("
                SELECT rowsecurity
                FROM pg_tables
                WHERE schemaname = ? AND tablename = ?
            ", [$table->table_schema, $table->table_name]);

            if (!$rlsEnabled->rowsecurity) {
                $violations[] = "RLS not enabled on: $tableName";
                continue;
            }

            // Check if RLS is forced
            $rlsForced = DB::selectOne("
                SELECT forcercsecurity
                FROM pg_tables
                WHERE schemaname = ? AND tablename = ?
            ", [$table->table_schema, $table->table_name]);

            if (!$rlsForced->forcercsecurity) {
                $violations[] = "RLS not forced on: $tableName";
            }

            // Check if RLS policies exist
            $policyCount = DB::selectOne("
                SELECT COUNT(*) as count
                FROM pg_policies
                WHERE tablename = ? AND schemaname = ?
            ", [$table->table_name, $table->table_schema]);

            if ($policyCount->count === 0) {
                $violations[] = "No RLS policies found on: $tableName";
            }
        }

        if (!empty($violations)) {
            $this->error('RLS violations found:');
            foreach ($violations as $violation) {
                $this->error("  - $violation");
            }
            return 1;
        }

        $this->info('✅ All RLS policies validated successfully');
        return 0;
    }
}
```

### **Layer 2: Model Validation**

#### **Model Constitutional Compliance Check**
```php
// app/Console/Commands/ValidateModels.php

class ValidateModels extends Command
{
    protected $signature = 'validate:models';
    protected $description = 'Validate model constitutional compliance';

    public function handle(): int
    {
        $violations = [];
        $modelFiles = glob(app_path('Models/**/*.php'));

        foreach ($modelFiles as $modelFile) {
            $content = file_get_contents($modelFile);
            $className = $this->extractClassName($content);

            // Skip if it's a base model or abstract class
            if (str_contains($content, 'abstract class') || str_contains($content, 'BaseModel')) {
                continue;
            }

            // Check 1: HasUuids trait
            if (!str_contains($content, 'HasUuids')) {
                $violations[] = "Model {$className}: Missing HasUuids trait";
            }

            // Check 2: BelongsToCompany trait (for tenant models)
            if (str_contains($content, 'acct.') || str_contains($content, 'ledger.') || str_contains($content, 'ops.')) {
                if (!str_contains($content, 'BelongsToCompany')) {
                    $violations[] = "Model {$className}: Missing BelongsToCompany trait for tenant model";
                }
            }

            // Check 3: UUID Configuration
            if (!str_contains($content, '$keyType = \'string\'')) {
                $violations[] = "Model {$className}: Missing keyType configuration";
            }

            if (!str_contains($content, '$incrementing = false')) {
                $violations[] = "Model {$className}: Missing incrementing = false";
            }

            // Check 4: Proper table specification
            if (!str_contains($content, 'protected $table') && !str_contains($content, 'BaseModel')) {
                $violations[] = "Model {$className}: Missing table specification";
            }

            // Check 5: Typed relationships
            $this->validateRelationships($content, $className, $violations);

            // Check 6: Business logic methods
            $this->validateBusinessLogic($content, $className, $violations);
        }

        if (!empty($violations)) {
            $this->error('Model violations found:');
            foreach ($violations as $violation) {
                $this->error("  - $violation");
            }
            return 1;
        }

        $this->info('✅ All models validated successfully');
        return 0;
    }

    private function validateRelationships(string $content, string $className, array &$violations): void
    {
        preg_match_all('/public function (\w+)\(\): (\w+)/', $content, $matches);

        foreach ($matches[1] as $index => $methodName) {
            $returnType = $matches[2][$index];

            // Should have proper relationship return types
            $validReturnTypes = [
                'BelongsTo', 'HasMany', 'HasOne', 'BelongsToMany',
                'MorphMany', 'MorphOne', 'MorphTo'
            ];

            if (!in_array($returnType, $validReturnTypes)) {
                $violations[] = "Model {$className}: Relationship {$methodName} has invalid return type {$returnType}";
            }
        }
    }

    private function validateBusinessLogic(string $content, string $className, array &$violations): void
    {
        // Check for boolean return methods that should exist
        $expectedBooleanMethods = ['isActive', 'isDeleted', 'canBeDeleted'];

        foreach ($expectedBooleanMethods as $method) {
            if (!str_contains($content, "public function {$method}()")) {
                $violations[] = "Model {$className}: Missing business logic method {$method}";
            }
        }
    }
}
```

### **Layer 3: Controller Validation**

#### **Controller Constitutional Compliance Check**
```php
// app/Console/Commands/ValidateControllers.php

class ValidateControllers extends Command
{
    protected $signature = 'validate:controllers';
    protected $description = 'Validate controller constitutional compliance';

    public function handle(): int
    {
        $violations = [];
        $controllerFiles = glob(app_path('Http/Controllers/**/*.php'));

        foreach ($controllerFiles as $controllerFile) {
            $content = file_get_contents($controllerFile);
            $className = $this->extractClassName($content);

            // Check 1: No direct Request injection
            if (preg_match('/Request \$request(?!.*FormRequest)/', $content)) {
                $violations[] = "Controller {$className}: Direct Request injection found (use FormRequest)";
            }

            // Check 2: No direct service calls
            if (preg_match('/new \w+Service\(\)/', $content)) {
                $violations[] = "Controller {$className}: Direct service call found (use Command Bus)";
            }

            // Check 3: No direct model access in controllers
            $directModelAccess = [
                'Customer::find(',
                'Invoice::create(',
                'User::where(',
                'DB::table('
            ];

            foreach ($directModelAccess as $pattern) {
                if (str_contains($content, $pattern)) {
                    $violations[] = "Controller {$className}: Direct model/database access found";
                    break;
                }
            }

            // Check 4: Proper response format
            $this->validateResponseFormat($content, $className, $violations);

            // Check 5: Error handling patterns
            $this->validateErrorHandling($content, $className, $violations);
        }

        if (!empty($violations)) {
            $this->error('Controller violations found:');
            foreach ($violations as $violation) {
                $this->error("  - $violation");
            }
            return 1;
        }

        $this->info('✅ All controllers validated successfully');
        return 0;
    }

    private function validateResponseFormat(string $content, string $className, array &$violations): void
    {
        // Look for non-standard JSON responses
        if (preg_match('/return response\(\)\->json\((?!.*success.*data.*message)/', $content)) {
            $violations[] = "Controller {$className}: Non-standard JSON response format";
        }
    }

    private function validateErrorHandling(string $content, string $className, array &$violations): void
    {
        // Check for try-catch blocks
        if (preg_match('/try\s*\{.*\}\s*catch.*\}/s', $content)) {
            // Should have proper error handling
            if (!str_contains($content, 'Log::error') && !str_contains($content, 'logger')) {
                $violations[] = "Controller {$className}: Try-catch without proper logging";
            }
        }
    }
}
```

### **Layer 4: Frontend Validation**

#### **Vue Component Constitutional Compliance Check**
```bash
#!/bin/bash
# scripts/validate-frontend-components.sh

echo "🔍 Validating Frontend Constitutional Compliance..."

VIOLATIONS=0

# Check 1: All components use Composition API
echo "  Checking Composition API usage..."
OPTIONS_API_COMPONENTS=$(find resources/js/ -name "*.vue" -exec grep -l "export default {" {} \;)

if [ ! -z "$OPTIONS_API_COMPONENTS" ]; then
    echo "❌ Found Options API components:"
    echo "$OPTIONS_API_COMPONENTS"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# Check 2: All components use PrimeVue
echo "  Checking PrimeVue compliance..."
NON_PRIMEVUE_COMPONENTS=$(find resources/js/ -name "*.vue" -exec grep -l "<input\|<select\|<button" {} \;)

if [ ! -z "$NON_PRIMEVUE_COMPONENTS" ]; then
    echo "❌ Found non-PrimeVue components:"
    echo "$NON_PRIMEVUE_COMPONENTS"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# Check 3: All forms use Inertia.js
echo "  Checking Inertia.js form usage..."
FETCH_BASED_FORMS=$(find resources/js/ -name "*.vue" -exec grep -l "fetch.*POST\|axios.*POST" {} \;)

if [ ! -z "$FETCH_BASED_FORMS" ]; then
    echo "❌ Found fetch-based forms:"
    echo "$FETCH_BASED_FORMS"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

if [ $VIOLATIONS -gt 0 ]; then
    echo "❌ Frontend validation failed with $VIOLATIONS violations"
    exit 1
fi

echo "✅ Frontend validation passed!"
```

### **Layer 5: Integration Validation**

#### **End-to-End Workflow Validation**
```php
// tests/Feature/ConstitutionalIntegrationTest.php

class ConstitutionalIntegrationTest extends TestCase
{
    /** @test */
    public function complete_user_journey_follows_constitution()
    {
        // Setup test environment
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'admin']);

        // Test 1: User registration follows constitutional patterns
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password'
        ])
        ->assertRedirect('/dashboard');

        // Test 2: Company creation follows constitutional patterns
        $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Test Company',
            'base_currency' => 'USD'
        ])
        ->assertRedirect();

        // Test 3: Customer creation follows all patterns
        $response = $this->actingAs($user)
        ->post('/api/customers', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'status' => 'active',
            'credit_limit' => 10000
        ], [
            'Idempotency-Key' => Str::uuid()
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'id',
                         'customer_number',
                         'name',
                         'outstanding_balance'
                     ],
                     'message'
                 ]);

        $customerId = $response->json('data.id');

        // Test 4: RLS prevents cross-tenant access
        $otherUser = User::factory()->create();
        $otherCompany = Company::factory()->create();
        $otherUser->companies()->attach($otherCompany, ['role' => 'admin']);

        $this->actingAs($otherUser)
        ->get("/api/customers/{$customerId}")
        ->assertStatus(404); // RLS blocks access

        // Test 5: Update follows command bus pattern
        $this->actingAs($user)
        ->put("/api/customers/{$customerId}", [
            'name' => 'Updated Customer',
            'credit_limit' => 15000
        ], [
            'Idempotency-Key' => Str::uuid()
        ])
        ->assertJson([
            'success' => true,
            'message' => 'Customer updated successfully'
        ]);

        // Test 6: Frontend can display data correctly
        $this->actingAs($user)
        ->get('/customers')
        ->assertOk()
        ->assertSee('Updated Customer');
    }

    /** @test */
    public function command_bus_integration_works_correctly()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company, ['role' => 'admin']);

        // Test command bus integration
        $context = new ServiceContext($user, $company);

        $customer = Bus::dispatch('customers.create', [
            'company_id' => $company->id,
            'name' => 'Command Bus Test Customer',
            'email' => 'commandbus@test.com'
        ], $context);

        $this->assertNotNull($customer->id);
        $this->assertEquals($company->id, $customer->company_id);
        $this->assertDatabaseHas('audit.entries', [
            'action' => 'customer_created',
            'entity_id' => $customer->id,
            'user_id' => $user->id
        ]);
    }

    /** @test */
    public function frontend_components_use_primevue_exclusively()
    {
        $this->get('/customers')
        ->assertOk()
        ->assertSee('p-datatable') // PrimeVue DataTable
        ->assertSee('p-button') // PrimeVue Button
        ->assertSee('p-inputtext'); // PrimeVue Input
    }
}
```

### **Performance and Security Validation**

#### **Performance Regression Test**
```php
// tests/Performance/PerformanceRegressionTest.php

class PerformanceRegressionTest extends TestCase
{
    /** @test */
    public function customer_list_performance_within_threshold()
    {
        // Create test data
        Customer::factory()->count(1000)->create();

        $start = microtime(true);

        $response = $this->get('/api/customers');

        $duration = (microtime(true) - $start) * 1000;

        $response->assertSuccessful();

        // Should complete within 500ms
        $this->assertLessThan(500, $duration, 'Customer list too slow: ' . $duration . 'ms');
    }

    /** @test */
    public function no_n_plus_one_queries()
    {
        Customer::factory()
            ->has(Invoice::factory()->count(5))
            ->count(100)
            ->create();

        DB::enableQueryLog();

        $response = $this->get('/api/customers');

        $queryCount = count(DB::getQueryLog());

        $response->assertSuccessful();

        // Should be able to fetch 100 customers with 5 invoices each in < 20 queries
        $this->assertLessThan(20, $queryCount, "Too many queries: $queryCount");

        DB::disableQueryLog();
    }
}
```

#### **Security Validation**
```php
// tests/Security/SecurityValidationTest.php

class SecurityValidationTest extends TestCase
{
    /** @test */
    public function rls_prevents_cross_tenant_data_access()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $user1 = User::factory()->create();
        $user1->companies()->attach($company1, ['role' => 'admin']);

        $user2 = User::factory()->create();
        $user2->companies()->attach($company2, ['role' => 'admin']);

        $customer1 = Customer::factory()->create(['company_id' => $company1->id]);
        $customer2 = Customer::factory()->create(['company_id' => $company2->id]);

        // User1 should only see their company's customers
        $this->actingAs($user1)
        ->get('/api/customers')
        ->assertJsonFragment(['name' => $customer1->name])
        ->assertJsonMissing(['name' => $customer2->name]);

        // User2 should only see their company's customers
        $this->actingAs($user2)
        ->get('/api/customers')
        ->assertJsonFragment(['name' => $customer2->name])
        ->assertJsonMissing(['name' => $customer1->name]);
    }

    /** @test */
    public function all_endpoints_require_proper_authorization()
    {
        $unauthorizedUser = User::factory()->create();

        $endpoints = [
            ['GET', '/api/customers'],
            ['POST', '/api/customers'],
            ['PUT', '/api/customers/fake-id'],
            ['DELETE', '/api/customers/fake-id']
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $this->actingAs($unauthorizedUser)
            ->json($method, $endpoint)
            ->assertStatus(403);
        }
    }
}
```

### **Comprehensive Quality Check Command**

```bash
#!/bin/bash
# scripts/comprehensive-quality-check.sh

echo "🚀 Running Comprehensive Quality Validation..."

TOTAL_VIOLATIONS=0

# 1. Database Validation
echo -e "\n1. Database Validation..."
if php artisan validate:rls-policies > /dev/null 2>&1; then
    echo "  ✅ RLS Policies: PASS"
else
    echo "  ❌ RLS Policies: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

if php artisan validate:models > /dev/null 2>&1; then
    echo "  ✅ Models: PASS"
else
    echo "  ❌ Models: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

# 2. Code Quality
echo -e "\n2. Code Quality..."
if composer quality-check > /dev/null 2>&1; then
    echo "  ✅ Code Quality: PASS"
else
    echo "  ❌ Code Quality: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

# 3. Frontend Validation
echo -e "\n3. Frontend Validation..."
if ./scripts/validate-frontend-components.sh > /dev/null 2>&1; then
    echo "  ✅ Frontend: PASS"
else
    echo "  ❌ Frontend: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

# 4. Testing
echo -e "\n4. Testing..."
if php artisan test tests/Feature/ConstitutionalIntegrationTest.php > /dev/null 2>&1; then
    echo "  ✅ Integration Tests: PASS"
else
    echo "  ❌ Integration Tests: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

if php artisan test tests/Security/SecurityValidationTest.php > /dev/null 2>&1; then
    echo "  ✅ Security Tests: PASS"
else
    echo "  ❌ Security Tests: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

# 5. Performance
echo -e "\n5. Performance..."
if php artisan test tests/Performance/PerformanceRegressionTest.php > /dev/null 2>&1; then
    echo "  ✅ Performance Tests: PASS"
else
    echo "  ❌ Performance Tests: FAIL"
    TOTAL_VIOLATIONS=$((TOTAL_VIOLATIONS + 1))
fi

# Final Report
echo -e "\n📊 QUALITY VALIDATION SUMMARY"
echo "=============================="

if [ $TOTAL_VIOLATIONS -eq 0 ]; then
    echo "✅ ALL VALIDATIONS PASSED"
    echo "🎉 System is constitutionally compliant!"
    exit 0
else
    echo "❌ VALIDATION FAILURES: $TOTAL_VIOLATIONS"
    echo "⚠️  Please address violations before proceeding to production"
    exit 1
fi
```

---

## **VALIDATION EXECUTION PLAN**

### **Pre-Deployment Validation**
1. **Database Layer Validation** - All migrations and RLS policies
2. **Model Layer Validation** - Traits, relationships, business logic
3. **Controller Layer Validation** - Command bus, FormRequest, responses
4. **Frontend Layer Validation** - PrimeVue, Composition API, Inertia.js
5. **Integration Validation** - End-to-end workflows
6. **Security Validation** - RLS isolation, authorization
7. **Performance Validation** - Query optimization, response times

### **Post-Deployment Validation**
1. **Critical Path Monitoring** - User registration, login, basic workflows
2. **Error Rate Monitoring** - 500 errors, validation failures
3. **Performance Monitoring** - Page load times, API response times
4. **Security Monitoring** - Cross-tenant access attempts, authorization failures
5. **Audit Log Monitoring** - All financial operations logged

---

This comprehensive validation system ensures that all remediated code meets constitutional requirements and maintains system integrity across all layers of the application.