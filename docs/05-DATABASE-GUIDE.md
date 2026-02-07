# Database Guide

**Last Updated**: 2025-02-01  
**Purpose**: Database architecture, schema contracts, migrations, and RLS  
**Audience**: Developers working with database schema

---

## Table of Contents

1. [Database Architecture](#1-database-architecture)
2. [Schema Organization](#2-schema-organization)
3. [Row-Level Security (RLS)](#3-row-level-security-rls)
4. [Migrations](#4-migrations)
5. [Models](#5-models)
6. [Schema Contracts](#6-schema-contracts)
7. [Common Patterns](#7-common-patterns)

---

## 1. Database Architecture

### Multi-Schema Design

Haasib uses PostgreSQL with multiple schemas for data isolation:

```
public          - Reference data (currencies)
auth            - Users, companies, permissions
acct            - Accounting (GL, AR, AP)
bank            - Banking & reconciliation
inv             - Inventory & items
pay             - Payroll & HR
crm             - Customer relationships
tax             - Tax configuration
audit           - Audit logs
```

### Why Multi-Schema?

1. **Logical Organization**: Related tables grouped together
2. **Access Control**: Schema-level permissions possible
3. **Clear Boundaries**: Easy to understand data domains
4. **Future Scaling**: Can split to different databases if needed

---

## 2. Schema Organization

### 2.1 Schema Decision Tree

```
Data Type → Schema
─────────────────
User/Company/Permission → auth
Financial/Invoices/Customers → acct
Banking/Reconciliation → bank
Items/Warehouses/Stock → inv
Payroll/Employees → pay
CRM/Marketing → crm
Tax/Compliance → tax
Logs/Audit Trail → audit
```

### 2.2 Schema Reference

| Schema | Tables | Description |
|--------|--------|-------------|
| **public** | currencies | ISO 4217 reference |
| **auth** | users, companies, roles, permissions, invitations | Authentication & tenancy |
| **acct** | accounts, customers, invoices, bills, payments | General ledger & transactions |
| **bank** | company_bank_accounts, transactions, reconciliations | Banking operations |
| **inv** | items, warehouses, stock_levels, movements | Inventory management |
| **pay** | employees, payroll_runs, payslips, leave | Payroll & HR |
| **crm** | leads, campaigns, activities | Customer relationships |
| **tax** | tax_registrations, rates, rules | Tax compliance |
| **audit** | activity_logs | Audit trail |

### 2.3 Example Schema Layout

```sql
-- Public schema
CREATE TABLE public.currencies (
    code CHAR(3) PRIMARY KEY,
    name VARCHAR(100),
    symbol VARCHAR(10),
    decimal_places INT DEFAULT 2
);

-- Auth schema
CREATE TABLE auth.users (
    id UUID PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    name VARCHAR(255),
    password_hash VARCHAR(255),
    created_at TIMESTAMP
);

CREATE TABLE auth.companies (
    id UUID PRIMARY KEY,
    name VARCHAR(255),
    slug VARCHAR(100) UNIQUE,
    base_currency CHAR(3) REFERENCES public.currencies(code),
    created_at TIMESTAMP
);

-- Accounting schema
CREATE TABLE acct.accounts (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    account_number VARCHAR(50),
    name VARCHAR(255),
    type VARCHAR(50),  -- asset, liability, equity, revenue, expense
    subtype VARCHAR(50),
    is_bank_account BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
);

CREATE TABLE acct.invoices (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    customer_id UUID REFERENCES acct.customers(id),
    invoice_number VARCHAR(50),
    invoice_date DATE,
    due_date DATE,
    total_amount DECIMAL(15,2),
    status VARCHAR(20),  -- draft, sent, paid, overdue, void
    created_at TIMESTAMP
);
```

---

## 3. Row-Level Security (RLS)

### 3.1 What is RLS?

Row-Level Security automatically filters queries based on the current company context:

```sql
-- Set company context
SET app.current_company_id = 'abc-123';

-- Query without explicit WHERE clause
SELECT * FROM acct.invoices;

-- RLS policy automatically applies:
-- WHERE company_id = current_setting('app.current_company_id')::uuid
```

### 3.2 Required on All Tenant Tables

Every table with `company_id` MUST have RLS enabled:

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
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            // ... other columns
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');
        });

        // Enable RLS (REQUIRED)
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices FORCE ROW LEVEL SECURITY');

        // Create policy
        DB::statement("
            CREATE POLICY invoices_company_isolation ON acct.invoices
            FOR ALL 
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invoices_company_isolation ON acct.invoices');
        Schema::dropIfExists('acct.invoices');
    }
};
```

### 3.3 RLS Policy Components

| Component | Purpose |
|-----------|---------|
| `ENABLE ROW LEVEL SECURITY` | Turns on RLS for the table |
| `FORCE ROW LEVEL SECURITY` | Applies to table owner too |
| `USING` clause | Filters SELECT, UPDATE, DELETE |
| `WITH CHECK` clause | Validates INSERT, UPDATE |

### 3.4 Testing RLS

```php
// In tinker or test
DB::statement("SET app.current_company_id = 'company-uuid'");

// Should only return invoices for that company
Invoice::all();

// Change context
DB::statement("SET app.current_company_id = 'different-company'");

// Now returns different data (or empty if none)
Invoice::all();
```

---

## 4. Migrations

### 4.1 Migration Standards

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
        // 1. Create schema if needed
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        // 2. Create table with UUID primary key
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            
            // Business columns
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->unique(['company_id', 'invoice_number']);
        });

        // 3. Enable RLS
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices FORCE ROW LEVEL SECURITY');

        // 4. Create RLS policy
        DB::statement("
            CREATE POLICY invoices_company_isolation ON acct.invoices
            FOR ALL 
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP POLICY IF EXISTS invoices_company_isolation ON acct.invoices');
        Schema::dropIfExists('acct.invoices');
    }
};
```

### 4.2 Creating Migrations

```bash
# Create migration
php artisan make:migration create_invoices_table

# Add column
php artisan make:migration add_due_date_to_invoices_table

# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh start (careful!)
php artisan migrate:fresh --seed
```

### 4.3 Adding Columns

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->string('reference', 100)->nullable()->after('invoice_number');
            $table->index(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
```

### 4.4 Migration Checklist

- [ ] Uses `uuid('id')->primary()` (never `$table->id()`)
- [ ] Has `company_id` UUID for tenant tables
- [ ] Schema prefix on table name (`acct.table`)
- [ ] Foreign keys reference correct schemas
- [ ] RLS enabled and policy created
- [ ] Indexes for query performance
- [ ] Timestamps and softDeletes included
- [ ] Down method cleans up properly

---

## 5. Models

### 5.1 Model Standards

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    // Database connection
    protected $connection = 'pgsql';

    // Schema-qualified table name
    protected $table = 'acct.invoices';

    // UUID configuration (REQUIRED)
    protected $keyType = 'string';
    public $incrementing = false;

    // Mass assignable fields (from schema contract)
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'total_amount',
        'status',
        'notes',
    ];

    // Attribute casting
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    // Business logic methods
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->status !== 'paid';
    }
}
```

### 5.2 Model Checklist

- [ ] Extends Model (or BaseModel if exists)
- [ ] Uses HasFactory, SoftDeletes if needed
- [ ] `$connection = 'pgsql'`
- [ ] `$table` with schema prefix (`acct.table`)
- [ ] `$keyType = 'string'` for UUID
- [ ] `$incrementing = false` for UUID
- [ ] `$fillable` matches schema contract
- [ ] `$casts` for proper type handling
- [ ] Typed relationships with return types
- [ ] Business logic methods for entity behavior

### 5.3 Relationships

```php
// BelongsTo (Child → Parent)
public function company(): BelongsTo
{
    return $this->belongsTo(Company::class);
}

// HasMany (Parent → Children)
public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class);
}

// Scoped to company
public function companyInvoices(): HasMany
{
    return $this->hasMany(Invoice::class)
        ->where('company_id', app(CurrentCompany::class)->get()->id);
}
```

---

## 6. Schema Contracts

### 6.1 What are Schema Contracts?

Schema contracts define the "source of truth" for database tables:

- Table columns and types
- Validation rules
- `$fillable` and `$casts` for models
- Relationships

Located in: `docs/contracts/`

### 6.2 Reading Schema Contracts

Before creating/modifying database:

1. Read `docs/contracts/{schema}-schema.md`
2. Check column definitions
3. Copy `$fillable` and `$casts` to model
4. Follow validation rules

Example: `docs/contracts/acct-schema.md`

```markdown
## Table: acct.invoices

### Columns
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | uuid | no | auto | Primary key |
| company_id | uuid | no | - | FK to auth.companies |
| invoice_number | varchar(50) | no | - | Unique per company |
| invoice_date | date | no | - | |
| total_amount | decimal(15,2) | no | 0 | |
| status | varchar(20) | no | draft | draft, sent, paid, void |

### Model Configuration
```php
protected $fillable = [
    'company_id',
    'invoice_number', 
    'invoice_date',
    'total_amount',
    'status',
];

protected $casts = [
    'invoice_date' => 'date',
    'total_amount' => 'decimal:2',
];
```

### Validation Rules
- invoice_number: required|string|max:50
- invoice_date: required|date
- total_amount: required|numeric|min:0
```

### 6.3 Schema Contract Index

| Contract | Schema | Tables |
|----------|--------|--------|
| `00-master-index.md` | - | Index of all contracts |
| `auth-contract.md` | auth | users, companies, roles |
| `acct-schema.md` | acct | invoices, customers, accounts |
| `banking-schema.md` | bank | accounts, transactions |
| `inventory-schema.md` | inv | items, warehouses |
| `payroll-schema.md` | pay | employees, payrolls |
| `crm-schema.md` | crm | leads, campaigns |
| `multicurrency-rules.md` | - | Currency handling |

---

## 7. Common Patterns

### 7.1 UUID Primary Keys

All tables use UUID primary keys:

```php
// Migration
$table->uuid('id')->primary();

// Model
protected $keyType = 'string';
public $incrementing = false;

// Foreign keys
$table->uuid('company_id');
$table->foreign('company_id')->references('id')->on('auth.companies');
```

### 7.2 Company Context Queries

```php
// Get current company
$company = app(CurrentCompany::class)->get();

// Query scoped to company
$invoices = $company->invoices()->get();

// Or explicitly
$invoices = Invoice::where('company_id', $company->id)->get();
// (RLS also enforces this automatically)
```

### 7.3 Decimal/Money Fields

Always use `decimal(15,2)` for money:

```php
// Migration
$table->decimal('total_amount', 15, 2)->default(0);

// Model
protected $casts = [
    'total_amount' => 'decimal:2',
];
```

### 7.4 Soft Deletes

Use soft deletes for data integrity:

```php
// Migration
$table->softDeletes();

// Model
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;
}

// Query
Invoice::all();              // Excludes soft-deleted
Invoice::withTrashed()->all(); // Includes soft-deleted
Invoice::onlyTrashed()->all(); // Only soft-deleted
```

### 7.5 Timestamps

Always include timestamps:

```php
// Migration
$table->timestamps();  // created_at, updated_at

// Automatic - no model config needed
```

### 7.6 Foreign Key Constraints

Always use foreign keys for referential integrity:

```php
$table->foreign('company_id')
    ->references('id')
    ->on('auth.companies')
    ->onDelete('cascade');  // or 'restrict', 'set null'
```

### 7.7 Indexing Strategy

Add indexes for performance:

```php
// Single column
$table->index(['company_id']);

// Composite (order matters - most selective first)
$table->index(['company_id', 'status']);
$table->index(['company_id', 'created_at']);

// Unique
$table->unique(['company_id', 'invoice_number']);
```

---

## Related Documentation

- [01-ARCHITECTURE.md](01-ARCHITECTURE.md) - System architecture
- [02-DEVELOPMENT-STANDARDS.md](02-DEVELOPMENT-STANDARDS.md) - Coding standards
- `docs/contracts/00-master-index.md` - Schema contract index
- `AI_PROMPTS/DATABASE_SCHEMA_REMEDIATION.md` - Remediation patterns
