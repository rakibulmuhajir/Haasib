# Haasib Quality Gates & Automation

> **Automated enforcement of your architectural standards**
>
> **Implementation**: Pre-commit hooks + CI checks + Development tools
>
> **Last Updated**: 2025-11-13

---

## Overview

**Purpose**: Prevent integration issues by enforcing your existing standards automatically
- **Block bad code** before it's committed
- **Ensure consistency** across all development work
- **Maintain architectural integrity** as specified in your Constitution
- **Enable fast development** by catching issues early

**Based on**: Your existing Constitution, Dos & Don'ts, and Team Memory

---

## Pre-Commit Quality Gates

### Installation & Setup
```bash
# 1. Install pre-commit hooks (already done)
cd /home/banna/projects/Haasib
chmod +x .husky/pre-commit

# 2. Test the pre-commit hook
./.husky/pre-commit
```

### What Pre-Commit Checks
1. **PHP Code Formatting** (Laravel Pint)
2. **PHP Syntax Validation**
3. **Database Migration Validation**
4. **Frontend Build Success**
5. **Security Audit**
6. **Constitutional Compliance**
7. **Critical Path Tests**

### Pre-Commit Hook (Current Implementation)
```bash
#!/bin/bash
# .husky/pre-commit

set -e

echo "🔍 Running Haasib quality checks..."

cd stack

# 1. PHP Code Formatting Check
if ! php artisan pint --test; then
    echo "❌ PHP code formatting issues found"
    echo "Run 'composer quality-fix' to fix formatting issues"
    exit 1
fi

# 2. PHP Syntax Check
if find app/ -name "*.php" -exec php -l {} \; | grep -q "Parse error"; then
    echo "❌ PHP syntax errors found"
    exit 1
fi

# 3. Database Migration Validation
if ! php artisan migrate:fresh --dry-run --force 2>/dev/null; then
    echo "❌ Database migration issues found"
    exit 1
fi

# 4. Frontend Build Check
if [ -f "package.json" ]; then
    if ! npm run build; then
        echo "❌ Frontend build failed"
        exit 1
    fi
fi

echo "✅ All quality checks passed!"
```

---

## Enhanced Quality Gates (Constitution Enforcement)

### Constitutional Compliance Checker
```bash
#!/bin/bash
# scripts/check-constitution.sh

echo "🏛️ Checking Constitutional Compliance..."

CONSTITUTION_VIOLATIONS=0

# Check 1: Multi-schema RLS patterns
if grep -r "Schema::" app/Database/Migrations/ | grep -q "auth\|acct\|ledger"; then
    echo "✅ Multi-schema patterns found"
else
    echo "❌ Missing multi-schema patterns"
    CONSTITUTION_VIOLATIONS=$((CONSTITUTION_VIOLATIONS + 1))
fi

# Check 2: Command Bus Usage
if grep -r "new.*Service()" app/Http/Controllers/ | grep -v "//"; then
    echo "❌ Direct service calls found in controllers (forbidden)"
    grep -r "new.*Service()" app/Http/Controllers/ | grep -v "//"
    CONSTITUTION_VIOLATIONS=$((CONSTITUTION_VIOLATIONS + 1))
else
    echo "✅ No direct service calls in controllers"
fi

# Check 3: UUID Primary Keys
if grep -r "\$table->id()" app/Database/Migrations/; then
    echo "❌ Integer primary keys found (should be UUID)"
    grep -r "\$table->id()" app/Database/Migrations/
    CONSTITUTION_VIOLATIONS=$((CONSTITUTION_VIOLATIONS + 1))
else
    echo "✅ No integer primary keys found"
fi

# Check 4: Form Request Usage
CONTROLLER_ACTIONS=$(find app/Http/Controllers/ -name "*.php" -exec grep -l "public function" {} \;)
for controller in $CONTROLLER_ACTIONS; do
    if grep -q "Request \$request" "$controller"; then
        echo "❌ Direct Request injection in $controller (use FormRequest)"
        CONSTITUTION_VIOLATIONS=$((CONSTITUTION_VIOLATIONS + 1))
    fi
done

if [ $CONSTITUTION_VIOLATIONS -eq 0 ]; then
    echo "✅ Constitutional compliance verified"
    exit 0
else
    echo "❌ Found $CONSTITUTION_VIOLATIONS constitutional violations"
    exit 1
fi
```

### Spatie Permission Compliance Checker
```bash
#!/bin/bash
# scripts/check-permissions.sh

echo "🔐 Checking Spatie Permission Compliance..."

VIOLATIONS=0

# Check User model has HasRoles trait
if ! grep -q "HasRoles" app/Models/User.php; then
    echo "❌ User model missing HasRoles trait"
    VIOLATIONS=$((VIOLATIONS + 1))
else
    echo "✅ User model has HasRoles trait"
fi

# Check for permission checking without trait
if grep -r "hasPermissionTo" app/ | grep -v User.php | grep -v "//"; then
    echo "❌ Permission checking found outside User model"
    VIOLATIONS=$((VIOLATIONS + 1))
else
    echo "✅ Permission checking properly scoped"
fi

# Check permission tables exist in migrations
if ! ls app/Database/Migrations/*permission*.php >/dev/null 2>&1; then
    echo "❌ Permission migrations not found"
    VIOLATIONS=$((VIOLATIONS + 1))
else
    echo "✅ Permission migrations found"
fi

exit $VIOLATIONS
```

### RLS Policy Validator
```bash
#!/bin/bash
# scripts/check-rls-policies.sh

echo "🔒 Checking RLS Policy Compliance..."

VIOLATIONS=0

# Check for tenant tables without RLS
SCHEMA_TABLES=$(grep -r "Schema::create" app/Database/Migrations/ | grep -E "(auth|acct|ledger|ops)\." | cut -d"'" -f2)
for table in $SCHEMA_TABLES; do
    # Check if corresponding RLS policy exists
    POLICY_FILE=$(find app/Database/Migrations/ -name "*.php" -exec grep -l "$table" {} \; | xargs grep -l "POLICY\|RLS")
    if [ -z "$POLICY_FILE" ]; then
        echo "❌ Table $table missing RLS policy"
        VIOLATIONS=$((VIOLATIONS + 1))
    fi
done

# Check for company_id in tenant tables
for table in $SCHEMA_TABLES; do
    if ! grep -r "company_id" app/Database/Migrations/ | grep "$table" >/dev/null; then
        echo "❌ Table $table missing company_id column"
        VIOLATIONS=$((VIOLATIONS + 1))
    fi
done

if [ $VIOLATIONS -eq 0 ]; then
    echo "✅ RLS policies verified"
else
    echo "❌ Found $VIOLATIONS RLS violations"
fi

exit $VIOLATIONS
```

---

## Database Quality Gates

### Migration Validator
```php
<?php
// app/Console/Commands/ValidateMigrations.php

class ValidateMigrations extends Command
{
    protected $signature = 'migrations:validate';
    protected $description = 'Validate migration files against constitutional requirements';

    public function handle()
    {
        $violations = [];

        $migrationFiles = glob(database_path('migrations/*.php'));

        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);

            // Check for prohibited patterns
            if (preg_match('/Schema::hasSchema\(/', $content)) {
                $violations[] = "Invalid Schema::hasSchema() in $file";
            }

            // Check for UUID requirements
            if (preg_match('/Schema::create.*(?:auth|acct|ledger|ops)/', $content)) {
                if (!preg_match('/uuid\(\).*primary\(\)/', $content)) {
                    $violations[] = "Missing UUID primary key in $file";
                }
            }

            // Check for RLS requirements
            if (preg_match('/Schema::create.*(?:auth|acct|ledger|ops)/', $content)) {
                if (!preg_match('/ENABLE ROW LEVEL SECURITY/', $content)) {
                    $violations[] = "Missing RLS enable in $file";
                }
            }
        }

        if (empty($violations)) {
            $this->info('✅ All migrations validated successfully');
            return 0;
        } else {
            $this->error('❌ Migration validation failed:');
            foreach ($violations as $violation) {
                $this->error("  - $violation");
            }
            return 1;
        }
    }
}
```

### Schema Integrity Checker
```php
<?php
// app/Console/Commands/CheckSchemaIntegrity.php

class CheckSchemaIntegrity extends Command
{
    protected $signature = 'schema:check-integrity';
    protected $description = 'Check database schema integrity and RLS compliance';

    public function handle()
    {
        $violations = [];

        // Check RLS policies on tenant tables
        $tenantTables = DB::select("
            SELECT table_schema, table_name
            FROM information_schema.tables
            WHERE table_schema IN ('auth', 'acct', 'ledger', 'ops')
        ");

        foreach ($tenantTables as $table) {
            $policyCount = DB::selectOne("
                SELECT COUNT(*) as count
                FROM pg_policies
                WHERE tablename = ?
            ", [$table->table_name])->count;

            if ($policyCount === 0) {
                $violations[] = "Table {$table->table_schema}.{$table->table_name} missing RLS policies";
            }

            // Check for company_id column
            $hasCompanyId = DB::selectOne("
                SELECT COUNT(*) as count
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name = 'company_id'
            ", [$table->table_schema, $table->table_name])->count;

            if ($hasCompanyId === 0) {
                $violations[] = "Table {$table->table_schema}.{$table->table_name} missing company_id column";
            }
        }

        // Check foreign key constraints
        $badFKs = DB::select("
            SELECT tc.table_schema, tc.table_name, kcu.column_name,
                   ccu.table_schema AS foreign_table_schema,
                   ccu.table_name AS foreign_table_name,
                   ccu.column_name AS foreign_column_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema IN ('auth', 'acct', 'ledger', 'ops')
              AND ccu.table_schema NOT IN ('auth', 'acct', 'ledger', 'ops')
        ");

        foreach ($badFKs as $fk) {
            $violations[] = "Foreign key references outside schema: {$fk->table_schema}.{$fk->table_name}.{$fk->column_name} → {$fk->foreign_table_schema}.{$fk->foreign_table_name}.{$fk->foreign_column_name}";
        }

        if (empty($violations)) {
            $this->info('✅ Schema integrity validated');
            return 0;
        } else {
            $this->error('❌ Schema integrity violations:');
            foreach ($violations as $violation) {
                $this->error("  - $violation");
            }
            return 1;
        }
    }
}
```

---

## Frontend Quality Gates

### PrimeVue Compliance Checker
```bash
#!/bin/bash
# scripts/check-primevue-compliance.sh

echo "🎨 Checking PrimeVue compliance..."

VIOLATIONS=0

# Check for non-PrimeVue components
RESOURCES_DIR="resources/js/Pages"
COMPONENTS_DIR="resources/js/Components"

# Look for custom table implementations
if find $RESOURCES_DIR $COMPONENTS_DIR -name "*.vue" -exec grep -l "<table\|<tbody\|<thead\|<tr\|<td\|<th" {} \; | grep -v "node_modules"; then
    echo "❌ Custom table implementations found (use PrimeVue DataTable)"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# Check for PrimeVue imports
if find $RESOURCES_DIR $COMPONENTS_DIR -name "*.vue" -exec grep -l "primevue" {} \; >/dev/null; then
    echo "✅ PrimeVue components being used"
else
    echo "⚠️  No PrimeVue imports found in components"
fi

# Check for proper button usage
if find $RESOURCES_DIR $COMPONENTS_DIR -name "*.vue" -exec grep -l "<button\|<input.*type.*submit\|<input.*type.*button" {} \; | grep -v "node_modules"; then
    echo "⚠️  HTML buttons found (should use PrimeVue Button)"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

# Check for proper form controls
if find $RESOURCES_DIR $COMPONENTS_DIR -name "*.vue" -exec grep -l "<input.*type.*text\|<input.*type.*email\|<select" {} \; | grep -v "node_modules"; then
    echo "⚠️  HTML form controls found (should use PrimeVue InputText, Dropdown)"
    VIOLATIONS=$((VIOLATIONS + 1))
fi

exit $VIOLATIONS
```

### Vue Component Structure Validator
```bash
#!/bin/bash
# scripts/check-vue-structure.sh

echo "🔧 Checking Vue component structure..."

VIOLATIONS=0

# Check for script setup usage
FILES=$(find resources/js -name "*.vue")
for file in $FILES; do
    if grep -q "<script>" "$file" && ! grep -q "script setup" "$file"; then
        echo "❌ $file uses Options API (should use Composition API with setup)"
        VIOLATIONS=$((VIOLATIONS + 1))
    fi
done

# Check for proper defineProps/defineEmits
for file in $FILES; do
    if grep -q "script setup" "$file" && ! grep -q "defineProps\|defineEmits" "$file"; then
        echo "⚠️  $file missing defineProps/defineEmits (in script setup)"
    fi
done

# Check for reactive refs vs direct assignment
for file in $FILES; do
    if grep -q "script setup" "$file" && grep -q "let.*=.*ref(" "$file"; then
        echo "⚠️  $file uses let with ref() (should use const for refs)"
    fi
done

exit $VIOLATIONS
```

---

## API Quality Gates

### Idempotency Key Validator
```php
<?php
// app/Http/Middleware/ValidateIdempotency.php

class ValidateIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        // Only check mutating methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header required for mutating requests'
            ], 422);
        }

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $idempotencyKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Idempotency-Key format (must be UUID v4)'
            ], 422);
        }

        return $next($request);
    }
}
```

### API Response Format Validator
```php
<?php
// Tests/Feature/ApiResponseFormatTest.php

class ApiResponseFormatTest extends TestCase
{
    /**
     * @dataProvider apiEndpointsProvider
     */
    public function test_api_response_format_consistency($method, $endpoint, $data = [])
    {
        $response = $this->json($method, $endpoint, $data);

        // Check successful response structure
        if ($response->isSuccessful()) {
            $response->assertJsonStructure([
                'success',
                'data',
                'message'
            ]);

            $this->assertTrue($response->json('success'));
        }

        // Check error response structure
        if ($response->isClientError() || $response->isServerError()) {
            $response->assertJsonStructure([
                'success',
                'message'
            ]);

            $this->assertFalse($response->json('success'));

            // Validation errors should have errors field
            if ($response->status() === 422) {
                $response->assertJsonStructure(['errors']);
            }
        }
    }

    public function apiEndpointsProvider()
    {
        return [
            ['POST', '/api/invoices', $this->getInvoiceData()],
            ['GET', '/api/invoices'],
            ['GET', '/api/invoices/invalid-id'],
        ];
    }

    private function getInvoiceData(): array
    {
        return [
            'customer_id' => 'invalid-uuid', // Will trigger validation error
            'items' => []
        ];
    }
}
```

---

## Critical Path Testing

### Regression Prevention Tests
```php
<?php
// Tests/Feature/CriticalPathTest.php

class CriticalPathTest extends TestCase
{
    /** @test */
    public function user_registration_still_works()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test' . time() . '@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    /** @test */
    public function user_login_still_works()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function company_creation_still_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/companies', [
            'name' => 'Test Company',
            'base_currency' => 'USD',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('auth.companies', [
            'name' => 'Test Company',
            'base_currency' => 'USD',
        ]);
    }

    /** @test */
    public function basic_invoice_creation_still_works()
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $user->companies()->attach($company, ['role' => 'admin']);

        $response = $this->actingAs($user)->post('/api/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00
                ]
            ]
        ], [
            'Idempotency-Key' => Str::uuid()
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('acct.invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'total_amount' => 100.00,
        ]);
    }
}
```

### Performance Regression Tests
```php
<?php
// Tests/Performance/DatabaseQueryPerformanceTest.php

class DatabaseQueryPerformanceTest extends TestCase
{
    /** @test */
    public function invoice_listing_query_performance()
    {
        // Create test data
        Invoice::factory()->count(1000)->create();

        $start = microtime(true);

        $response = $this->get('/api/invoices');

        $end = microtime(true);
        $duration = ($end - $start) * 1000; // Convert to milliseconds

        $response->assertSuccessful();

        // Should complete within 500ms
        $this->assertLessThan(500, $duration, 'Invoice listing took too long: ' . $duration . 'ms');
    }

    /** @test */
    public function no_n_plus_one_queries()
    {
        Invoice::factory()->count(100)
            ->hasItems(5)
            ->create();

        // Enable query counting
        DB::enableQueryLog();

        $response = $this->get('/api/invoices');

        $queryCount = count(DB::getQueryLog());

        // Should be able to fetch 100 invoices with 5 items each in < 20 queries
        $this->assertLessThan(20, $queryCount, "Too many queries: $queryCount");

        DB::disableQueryLog();
    }
}
```

---

## CI/CD Pipeline Integration

### GitHub Actions Workflow
```yaml
# .github/workflows/quality-gates.yml
name: Constitutional Quality Gates

on: [push, pull_request]

jobs:
  quality-checks:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.2
        extensions: pgsql, uuid

    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '20'
        cache: 'npm'
        cache-dependency-path: 'stack/package-lock.json'

    - name: Install Dependencies
      run: |
        cd stack
        composer install --prefer-dist --no-progress
        npm install

    - name: Run Quality Gates
      run: |
        cd stack
        composer quality-check

        # Run constitutional checks
        ../scripts/check-constitution.sh
        ../scripts/check-permissions.sh
        ../scripts/check-rls-policies.sh
        ../scripts/check-primevue-compliance.sh
        ../scripts/check-vue-structure.sh

    - name: Run Critical Path Tests
      run: |
        cd stack
        php artisan test tests/Feature/CriticalPathTest.php
        php artisan test tests/Performance/DatabaseQueryPerformanceTest.php

    - name: Check Migration Validation
      run: |
        cd stack
        php artisan migrations:validate
        php artisan schema:check-integrity
```

---

## Development Workflow Integration

### Quick Development Commands
```bash
# Available composer commands (already added to composer.json)
composer quality-check      # Run all quality checks
composer quality-fix        # Auto-fix formatting issues
composer quality            # Run quality with coverage
```

### Before Commit Checklist
```bash
#!/bin/bash
# scripts/pre-commit-checklist.sh

echo "📋 Pre-commit Quality Checklist:"

echo "  1. 📝 PHP formatting..."
php artisan pint --test && echo "     ✅ Formatted" || echo "     ❌ Formatting issues"

echo "  2. 🏛️ Constitutional compliance..."
../scripts/check-constitution.sh && echo "     ✅ Compliant" || echo "     ❌ Constitutional violations"

echo "  3. 🔐 Permission system..."
../scripts/check-permissions.sh && echo "     ✅ Permissions OK" || echo "     ❌ Permission issues"

echo "  4. 🔒 RLS policies..."
../scripts/check-rls-policies.sh && echo "     ✅ RLS OK" || echo "     ❌ RLS issues"

echo "  5. 🎨 PrimeVue compliance..."
../scripts/check-primevue-compliance.sh && echo "     ✅ UI OK" || echo "     ❌ UI issues"

echo "  6. 🧪 Critical tests..."
php artisan test tests/Feature/CriticalPathTest.php --quiet && echo "     ✅ Tests pass" || echo "     ❌ Tests failing"

echo "  7. 🏗️  Frontend build..."
npm run build --silent && echo "     ✅ Build OK" || echo "     ❌ Build fails"

echo ""
echo "🎯 Ready to commit!"
```

---

## Error Handling & Recovery

### Automated Fix Commands
```bash
#!/bin/bash
# scripts/auto-fix-issues.sh

echo "🔧 Attempting to auto-fix common issues..."

# Auto-fix PHP formatting
echo "  Fixing PHP formatting..."
composer pint

# Auto-fix database issues
echo "  Checking for database issues..."
php artisan migrate:status

# Auto-fix frontend issues
echo "  Installing missing dependencies..."
npm install

echo "✅ Auto-fix completed. Please review changes."
```

### Issue Detection & Reporting
```php
<?php
// app/Services/QualityGateService.php

class QualityGateService
{
    public function detectArchitectureViolations(): array
    {
        $violations = [];

        // Check for direct service calls in controllers
        $controllerFiles = glob(app_path('Http/Controllers/*.php'));
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/new\s+\w+Service\(\)/', $content)) {
                $violations[] = [
                    'type' => 'architecture',
                    'file' => $file,
                    'message' => 'Direct service instantiation found in controller',
                    'fix' => 'Use command bus: Bus::dispatch("action.name", $data, $context)'
                ];
            }
        }

        // Check for missing Form Requests
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/Request\s+\$request/', $content)) {
                $violations[] = [
                    'type' => 'validation',
                    'file' => $file,
                    'message' => 'Direct Request injection found',
                    'fix' => 'Use FormRequest classes for validation'
                ];
            }
        }

        return $violations;
    }
}
```

---

## Monitoring & Alerting

### Quality Metrics Dashboard
```php
<?php
// app/Http/Controllers/QualityMetricsController.php

class QualityMetricsController extends Controller
{
    public function index()
    {
        return response()->json([
            'code_quality_metrics' => [
                'php_files_checked' => $this->countPhpFiles(),
                'formatting_issues' => $this->countFormattingIssues(),
                'syntax_errors' => $this->countSyntaxErrors(),
                'architecture_violations' => $this->countArchitectureViolations(),
            ],
            'test_metrics' => [
                'tests_run' => $this->getTestCount(),
                'test_coverage' => $this->getTestCoverage(),
                'critical_path_tests' => $this->getCriticalPathTestResults(),
            ],
            'frontend_metrics' => [
                'vue_components' => $this->countVueComponents(),
                'primevue_compliance' => $this->checkPrimeVueCompliance(),
                'build_success' => $this->checkFrontendBuild(),
            ],
            'database_metrics' => [
                'rls_policies' => $this->countRLSPolicies(),
                'migration_issues' => $this->countMigrationIssues(),
                'schema_integrity' => $this->checkSchemaIntegrity(),
            ]
        ]);
    }
}
```

---

## Implementation Plan

### Phase 1: Immediate (Today)
1. **Remove my conflicting files**:
   ```bash
   rm DEVELOPMENT_GUIDELINES.md TESTING_PROTOCOL.md
   rm SETUP_QUALITY_CONTROLS.sh
   ```

2. **Update pre-commit hook** with enhanced checks:
   ```bash
   # Add constitutional compliance checks to .husky/pre-commit
   echo "../scripts/check-constitution.sh" >> .husky/pre-commit
   ```

3. **Create helper scripts**:
   ```bash
   mkdir -p scripts
   # Create the checker scripts shown above
   ```

### Phase 2: This Week
1. **Add to CI pipeline**
2. **Create quality metrics dashboard**
3. **Add automated fix capabilities**
4. **Set up monitoring and alerts**

### Phase 3: Next Week
1. **Team training on new standards**
2. **Documentation updates**
3. **Feedback collection and refinement**

---

**This automated system enforces YOUR existing standards, preventing the integration issues you've experienced while enabling fast, confident development.**