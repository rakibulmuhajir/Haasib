# Database Schema Remediation Prompt

## Task: Fix Database Schema Constitutional Violations

You are a **Database Architecture Expert** specialized in PostgreSQL schema remediation for multi-tenant systems.

## CURRENT VIOLATIONS TO FIX

### **Common Non-Compliant Patterns Found**

#### **1. Integer Primary Keys (CRITICAL)**
```php
// BEFORE (VIOLATION)
Schema::create('customers', function (Blueprint $table) {
    $table->id(); // ❌ Integer primary key
    $table->string('name');
    $table->timestamps();
});

// AFTER (CONSTITUTIONAL)
Schema::create('acct.customers', function (Blueprint $table) {
    $table->uuid('id')->primary(); // ✅ UUID primary key
    $table->uuid('company_id'); // ✅ Tenant isolation
    $table->string('customer_number', 50);
    $table->string('name');
    $table->timestamps();
    $table->softDeletes();
});
```

#### **2. Missing Multi-Schema Structure (CRITICAL)**
```php
// BEFORE (VIOLATION)
Schema::create('customers', function (Blueprint $table) {
    // No schema specified - goes to public schema
});

// AFTER (CONSTITUTIONAL)
// 1. Create schema first
DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

// 2. Create table in correct schema
Schema::create('acct.customers', function (Blueprint $table) {
    // Schema-compliant table definition
});
```

#### **3. Missing RLS Policies (CRITICAL)**
```php
// BEFORE (VIOLATION)
Schema::create('acct.customers', function (Blueprint $table) {
    // No RLS - cross-tenant data leak possible
});

// AFTER (CONSTITUTIONAL)
Schema::create('acct.customers', function (Blueprint $table) {
    // Table definition...
});

// Enable RLS (MANDATORY)
DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');

// Create RLS policy
DB::statement("
    CREATE POLICY customers_company_policy ON acct.customers
    FOR ALL
    USING (company_id = current_setting('app.current_company_id', true)::uuid)
    WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
");
```

#### **4. Missing Company Context (CRITICAL)**
```php
// BEFORE (VIOLATION)
Schema::create('acct.customers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    // ❌ Missing company_id - no tenant isolation
});

// AFTER (CONSTITUTIONAL)
Schema::create('acct.customers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('company_id'); // ✅ Tenant isolation
    $table->string('customer_number', 50);
    $table->string('name');

    // ✅ Foreign key to auth.companies
    $table->foreign('company_id')
          ->references('id')
          ->on('auth.companies')
          ->onDelete('cascade');

    // ✅ Unique constraints per company
    $table->unique(['company_id', 'customer_number']);
});
```

## COMPLETE MIGRATION TEMPLATE

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create schema if not exists
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.customers', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('customer_number', 50);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('credit_limit', 10, 2)->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'customer_number']);
            $table->unique(['company_id', 'email']);

            // Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'email']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for financial/business tables)
        DB::statement('
            CREATE TRIGGER customers_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customers
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create Function for Customer Number Generation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.generate_customer_number(p_company_id UUID)
            RETURNS TEXT AS $$
            DECLARE
                next_number INTEGER;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(customer_number FROM 6) AS INTEGER)), 0) + 1
                INTO next_number
                FROM acct.customers
                WHERE company_id = p_company_id;

                RETURN \'CUST-\' || LPAD(next_number::TEXT, 5, \'0\');
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP FUNCTION IF EXISTS acct.generate_customer_number(UUID)');
        DB::statement('DROP TRIGGER IF EXISTS customers_audit_trigger ON acct.customers');
        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        Schema::dropIfExists('acct.customers');
    }
};
```

## CHECKLIST FOR EVERY MIGRATION

### **✅ Must Include:**
- [ ] Schema specification (`auth/acct/ledger/ops`)
- [ ] UUID primary key with `$table->uuid('id')->primary()`
- [ ] `company_id` column for tenant tables
- [ ] Cross-schema foreign key references
- [ ] Unique constraints per company
- [ ] Performance indexes
- [ ] RLS enablement (`ENABLE ROW LEVEL SECURITY`)
- [ ] RLS policy with `USING` and `WITH CHECK`
- [ ] RLS forcing (`FORCE ROW LEVEL SECURITY`)
- [ ] Audit trigger for business tables
- [ ] Proper cleanup in `down()` method

### **❌ Must NOT Include:**
- [ ] `$table->id()` (integer primary keys)
- [ ] Tables in `public` schema (except system tables)
- [ ] Missing RLS policies on tenant tables
- [ ] Cross-schema foreign keys without proper references
- [ ] UUID functions without pgcrypto extension

## VALIDATION COMMANDS

```bash
# Test migration
php artisan migrate

# Check RLS policies
php artisan tinker
> DB::select("SELECT * FROM pg_policies WHERE tablename = 'customers'");

# Check table schema
php artisan tinker
> \d acct.customers

# Test RLS isolation
php artisan tinker
> DB::statement("SET app.current_company_id = 'your-company-uuid'");
> DB::select("SELECT COUNT(*) FROM acct.customers");
```

## COMMON PITFALLS TO AVOID

1. **Never use `Schema::hasSchema()`** - doesn't exist in Laravel
2. **Always enable pgcrypto before UUID functions** - `CREATE EXTENSION IF NOT EXISTS pgcrypto`
3. **Test rollback** - `php artisan migrate:rollback` must work
4. **Check for existing data** - migrations must be idempotent
5. **Use correct UUID casting** - `current_setting(..., true)::uuid`

Apply this template to ALL non-compliant migrations in your codebase.