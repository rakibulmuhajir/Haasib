# Database Schema Remediation Guide

**Task**: Fix database schema constitutional violations
**Location**: `modules/{ModuleName}/Database/Migrations/`

---

## 🎯 CRITICAL VIOLATIONS TO FIX

### 1. Integer Primary Keys → UUID
```php
// ❌ BEFORE
$table->id();

// ✅ AFTER
$table->uuid('id')->primary();
```

### 2. Wrong Schema → Correct Schema
```php
// ❌ BEFORE
Schema::create('customers', ...)

// ✅ AFTER
Schema::create('acct.customers', ...)
```

### 3. Missing RLS → Enable RLS
```php
// ✅ REQUIRED
DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');
DB::statement("CREATE POLICY customers_policy ON acct.customers
    USING (company_id = current_setting('app.current_company_id')::uuid)");
```

### 4. Missing Company Context → Add company_id
```php
// ✅ REQUIRED
$table->uuid('company_id');
$table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
$table->unique(['company_id', 'email']);
```

---

## 📋 COMPLETE MIGRATION TEMPLATE

**Copy this for every new table**:

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
        // Create schema if needed
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.customers', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Fields
            $table->string('customer_number', 50);
            $table->string('name');
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->decimal('credit_limit', 10, 2)->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');

            // Unique Constraints (per company)
            $table->unique(['company_id', 'customer_number']);
            $table->unique(['company_id', 'email']);

            // Performance Indexes
            $table->index(['company_id', 'status']);
        });

        // Enable RLS (MANDATORY)
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');

        // RLS Policy
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Audit Trigger (for business tables)
        DB::statement('
            CREATE TRIGGER customers_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.customers
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS customers_audit_trigger ON acct.customers');
        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        Schema::dropIfExists('acct.customers');
    }
};
```

---

## 🗂️ SCHEMA ASSIGNMENT RULES

| Module | Schema | Examples |
|--------|--------|----------|
| **Core** | `auth` | users, companies, permissions |
| **Accounting** | `acct` | customers, invoices, payments |
| **Hospitality** | `hsp` | bookings, rooms, guests |
| **CRM** | `crm` | leads, contacts, campaigns |

**Rule**: If a module needs a new entity, it goes in that module's schema.

---

## ✅ MIGRATION CHECKLIST

**Every migration MUST have**:
- [ ] Schema prefix (`acct.`, `hsp.`, etc.)
- [ ] UUID primary key
- [ ] `company_id` column (tenant tables)
- [ ] Cross-schema foreign keys
- [ ] Unique constraints per company
- [ ] RLS enabled + forced
- [ ] RLS policy created
- [ ] Audit trigger (business tables)
- [ ] Proper `down()` cleanup

---

## 🔍 VALIDATION COMMANDS

```bash
# Test migration
cd stack && php artisan migrate

# Check RLS policies
php artisan tinker
> DB::select("SELECT * FROM pg_policies WHERE tablename = 'customers'");

# Test RLS isolation
> DB::statement("SET app.current_company_id = 'company-uuid'");
> DB::table('acct.customers')->count(); // Should only show that company's data
```

---

## 🚫 COMMON MISTAKES

```php
// ❌ Don't use Schema::hasSchema() - doesn't exist
// ✅ Use DB::statement('CREATE SCHEMA IF NOT EXISTS ...')

// ❌ Don't forget pgcrypto extension
// ✅ Already enabled in base migration

// ❌ Don't use wrong UUID casting
// ✅ Use: current_setting(..., true)::uuid

// ❌ Don't create tables in public schema
// ✅ Always specify schema: acct.customers
```

---

**Reference**: `.specify/memory/constitution.md` for architectural rationale
