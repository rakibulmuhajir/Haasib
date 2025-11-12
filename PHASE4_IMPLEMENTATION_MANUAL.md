# Phase 4 Implementation Guide

## Overview
This manual provides detailed implementation requirements for Phase 4: Advanced Features & Integration modules that are currently missing from the Haasib system.

## Haasib Architecture Compliance

### Constitutional Requirements
All implementations MUST follow the Haasib Architecture Constitution:

**✅ Schema Domain Separation**: All tables must be placed in appropriate schemas (auth, acct, ledger, audit, ops)

**✅ Security-First Bookkeeping**:
- `company_id` scoping for all tenant data
- Row Level Security (RLS) policies using `current_setting('app.current_company_id')`
- Audit coverage via `audit_log()` helper

**✅ Test & Review Discipline**: Include feature tests for all migrations and handlers

**✅ Frontend Component Patterns**: Use PrimeVue components, debounced search, and null-safe data handling

### Implementation Patterns
- **UUID Primary Keys**: All new tables use UUID primary keys with `gen_random_uuid()`
- **Soft Deletes**: Implement `deleted_at TIMESTAMP NULL` for auditability
- **Audit Integration**: Use `audit_log()` helper for financial/security events
- **RLS Templates**: Follow existing migration patterns for RLS policies

## Current Implementation Status (Based on Testing Results)

### ✅ COMPLETED MODULES
- **Bank Reconciliation - Enhanced**: Fully implemented with professional UI
- **Trial Balance**: API-based implementation with microservice architecture
- **Product Model**: Comprehensive inventory tracking foundation (backend only)

### ❌ MISSING MODULES (Implementation Required)

## 1. Tax Management Module

### 1.1 Database Schema (acct schema)

```sql
-- Tax Agencies Table (acct.tax_agencies)
CREATE TABLE acct.tax_agencies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    name VARCHAR(255) NOT NULL,
    tax_id_number VARCHAR(100),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    filing_frequency VARCHAR(50) NOT NULL, -- monthly, quarterly, annually
    tax_type VARCHAR(50) NOT NULL, -- sales_tax, vat, income_tax, payroll_tax
    reporting_method VARCHAR(50), -- accrual, cash
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Tax Rates Table (acct.tax_rates)
CREATE TABLE acct.tax_rates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    tax_agency_id UUID REFERENCES acct.tax_agencies(id),
    name VARCHAR(255) NOT NULL,
    rate DECIMAL(8,4) NOT NULL CHECK (rate >= 0), -- percentage as decimal
    tax_type VARCHAR(50) NOT NULL, -- sales_tax, vat, use_tax, import_tax
    applies_to VARCHAR(50) NOT NULL, -- sales, purchases, both
    effective_date DATE NOT NULL,
    expiry_date DATE,
    is_compound BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Tax Returns Table (acct.tax_returns)
CREATE TABLE acct.tax_returns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    tax_agency_id UUID NOT NULL REFERENCES acct.tax_agencies(id),
    return_period_start DATE NOT NULL,
    return_period_end DATE NOT NULL,
    filing_date DATE,
    due_date NOT NULL,
    total_sales DECIMAL(15,2) DEFAULT 0 CHECK (total_sales >= 0),
    total_taxable_sales DECIMAL(15,2) DEFAULT 0 CHECK (total_taxable_sales >= 0),
    total_tax_exempt_sales DECIMAL(15,2) DEFAULT 0 CHECK (total_tax_exempt_sales >= 0),
    total_tax_collected DECIMAL(15,2) DEFAULT 0 CHECK (total_tax_collected >= 0),
    total_tax_due DECIMAL(15,2) DEFAULT 0 CHECK (total_tax_due >= 0),
    total_tax_paid DECIMAL(15,2) DEFAULT 0 CHECK (total_tax_paid >= 0),
    balance_due DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(50) NOT NULL, -- draft, filed, paid, overdue
    filing_reference VARCHAR(255),
    notes TEXT,
    created_by_user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Tax Payments Table (acct.tax_payments)
CREATE TABLE acct.tax_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    tax_return_id UUID REFERENCES acct.tax_returns(id),
    tax_agency_id UUID NOT NULL REFERENCES acct.tax_agencies(id),
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL CHECK (amount > 0),
    payment_method VARCHAR(50), -- check, electronic, wire
    reference_number VARCHAR(255),
    status VARCHAR(50), -- pending, completed, failed
    notes TEXT,
    created_by_user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- RLS Policies (add after table creation)
ALTER TABLE acct.tax_agencies ENABLE ROW LEVEL SECURITY;
ALTER TABLE acct.tax_rates ENABLE ROW LEVEL SECURITY;
ALTER TABLE acct.tax_returns ENABLE ROW LEVEL SECURITY;
ALTER TABLE acct.tax_payments ENABLE ROW LEVEL SECURITY;

CREATE POLICY tax_agencies_company_policy ON acct.tax_agencies
    USING (company_id = current_setting('app.current_company_id')::UUID);

CREATE POLICY tax_rates_company_policy ON acct.tax_rates
    USING (company_id = current_setting('app.current_company_id')::UUID);

CREATE POLICY tax_returns_company_policy ON acct.tax_returns
    USING (company_id = current_setting('app.current_company_id')::UUID);

CREATE POLICY tax_payments_company_policy ON acct.tax_payments
    USING (company_id = current_setting('app.current_company_id')::UUID);
```

### 1.2 Laravel Models

```php
<?php
// app/Models/TaxAgency.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxAgency extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'tax_id_number', 'contact_email',
        'contact_phone', 'filing_frequency', 'tax_type',
        'reporting_method', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'filing_date' => 'date',
        'due_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class);
    }

    public function taxReturns()
    {
        return $this->hasMany(TaxReturn::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

<?php
// app/Models/TaxRate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id', 'tax_agency_id', 'name', 'rate', 'tax_type',
        'applies_to', 'effective_date', 'expiry_date', 'is_compound', 'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'is_compound' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function taxAgency()
    {
        return $this->belongsTo(TaxAgency::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            });
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $date);
            });
    }
}
```

### 1.3 Controllers

```php
<?php
// app/Http/Controllers/TaxManagement/TaxAgencyController.php
namespace App\Http\Controllers\TaxManagement;

use App\Models\TaxAgency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxAgencyController extends Controller
{
    public function index(Request $request)
    {
        $query = TaxAgency::where('company_id', Auth::user()->current_company_id)
            ->with(['taxReturns' => function ($q) {
                $q->where('status', '!=', 'paid')->orderBy('due_date');
            }]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('tax_id_number', 'ILIKE', "%{$search}%");
            });
        }

        $agencies = $query->orderBy('name')->paginate(25);

        return Inertia::render('TaxManagement/Agencies/Index', [
            'agencies' => $agencies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('TaxManagement/Agencies/Create', [
            'filingFrequencies' => [
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
                'semi_annually' => 'Semi-Annually',
            ],
            'taxTypes' => [
                'sales_tax' => 'Sales Tax',
                'vat' => 'VAT',
                'income_tax' => 'Income Tax',
                'payroll_tax' => 'Payroll Tax',
                'property_tax' => 'Property Tax',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_id_number' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'filing_frequency' => 'required|in:monthly,quarterly,annually,semi_annually',
            'tax_type' => 'required|in:sales_tax,vat,income_tax,payroll_tax,property_tax',
            'reporting_method' => 'nullable|in:accrual,cash',
            'is_active' => 'boolean',
        ]);

        TaxAgency::create([
            'company_id' => Auth::user()->current_company_id,
            'created_by_user_id' => Auth::id(),
            ...$validated,
        ]);

        return redirect()->route('tax-agencies.index')
            ->with('success', 'Tax agency created successfully.');
    }
}
```

### 1.4 Routes (Add to web.php)

```php
// Tax Management Routes
Route::prefix('tax-management')->name('tax-management.')->middleware(['auth'])->group(function () {
    // Tax Agencies
    Route::prefix('agencies')->name('agencies.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TaxManagement\TaxAgencyController::class, 'index'])
            ->middleware('permission:tax.view')->name('index');
        Route::get('/create', [\App\Http\Controllers\TaxManagement\TaxAgencyController::class, 'create'])
            ->middleware('permission:tax.create')->name('create');
        Route::post('/', [\App\Http\Controllers\TaxManagement\TaxAgencyController::class, 'store'])
            ->middleware('permission:tax.create')->name('store');
    });

    // Tax Rates
    Route::prefix('tax-rates')->name('tax-rates.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TaxManagement\TaxRateController::class, 'index'])
            ->middleware('permission:tax.view')->name('index');
        Route::get('/create', [\App\Http\Controllers\TaxManagement\TaxRateController::class, 'create'])
            ->middleware('permission:tax.create')->name('create');
        Route::post('/', [\App\Http\Controllers\TaxManagement\TaxRateController::class, 'store'])
            ->middleware('permission:tax.create')->name('store');
    });

    // Tax Returns
    Route::prefix('tax-returns')->name('tax-returns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TaxManagement\TaxReturnController::class, 'index'])
            ->middleware('permission:tax.view')->name('index');
        Route::get('/create', [\App\Http\Controllers\TaxManagement\TaxReturnController::class, 'create'])
            ->middleware('permission:tax.create')->name('create');
        Route::post('/', [\App\Http\Controllers\TaxManagement\TaxReturnController::class, 'store'])
            ->middleware('permission:tax.create')->name('store');
    });
});
```

## 2. Budget Management Module

### 2.1 Database Schema (acct schema)

```sql
-- Budgets Table (acct.budgets)
CREATE TABLE acct.budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    fiscal_year_start DATE NOT NULL,
    fiscal_year_end DATE NOT NULL,
    status VARCHAR(50) NOT NULL, -- draft, active, closed
    total_budgeted_amount DECIMAL(15,2) DEFAULT 0 CHECK (total_budgeted_amount >= 0),
    total_actual_amount DECIMAL(15,2) DEFAULT 0 CHECK (total_actual_amount >= 0),
    variance_amount DECIMAL(15,2) DEFAULT 0,
    variance_percentage DECIMAL(5,2) DEFAULT 0,
    approval_status VARCHAR(50), -- pending, approved, rejected
    approved_by_user_id UUID REFERENCES auth.users(id),
    approved_at TIMESTAMP NULL,
    created_by_user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT valid_fiscal_year CHECK (fiscal_year_end > fiscal_year_start)
);

-- Budget Lines Table (acct.budget_lines)
CREATE TABLE acct.budget_lines (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_id UUID NOT NULL REFERENCES acct.budgets(id),
    account_id UUID REFERENCES acct.accounts(id),
    department_id UUID, -- if departmental budgeting
    name VARCHAR(255) NOT NULL,
    description TEXT,
    budgeted_amount DECIMAL(15,2) NOT NULL CHECK (budgeted_amount >= 0),
    actual_amount DECIMAL(15,2) DEFAULT 0 CHECK (actual_amount >= 0),
    variance_amount DECIMAL(15,2) DEFAULT 0,
    variance_percentage DECIMAL(5,2) DEFAULT 0,
    period_type VARCHAR(50), -- monthly, quarterly, yearly
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Budget Actuals Table (acct.budget_actuals)
CREATE TABLE acct.budget_actuals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_line_id UUID NOT NULL REFERENCES acct.budget_lines(id),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    actual_amount DECIMAL(15,2) NOT NULL CHECK (actual_amount >= 0),
    transaction_count INTEGER DEFAULT 0 CHECK (transaction_count >= 0),
    source VARCHAR(100), -- manual, imported, calculated
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT valid_budget_period CHECK (period_end >= period_start)
);

-- RLS Policies (add after table creation)
ALTER TABLE acct.budgets ENABLE ROW LEVEL SECURITY;
ALTER TABLE acct.budget_lines ENABLE ROW LEVEL SECURITY;
ALTER TABLE acct.budget_actuals ENABLE ROW LEVEL SECURITY;

CREATE POLICY budgets_company_policy ON acct.budgets
    USING (company_id = current_setting('app.current_company_id')::UUID);

CREATE POLICY budget_lines_company_policy ON acct.budget_lines
    USING (company_id = current_setting('app.current_company_id')::UUID);

CREATE POLICY budget_actuals_company_policy ON acct.budget_actuals
    USING (company_id = current_setting('app.current_company_id')::UUID);
```

### 2.2 Laravel Models

```php
<?php
// app/Models/Budget.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use function audit_log;

class Budget extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'acct.budgets';

    protected $fillable = [
        'company_id', 'name', 'description', 'fiscal_year_start',
        'fiscal_year_end', 'status', 'approval_status',
        'approved_by_user_id', 'approved_at', 'created_by_user_id'
    ];

    protected $casts = [
        'fiscal_year_start' => 'date',
        'fiscal_year_end' => 'date',
        'total_budgeted_amount' => 'decimal:2',
        'total_actual_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($budget) {
            audit_log('budget_created', [
                'budget_id' => $budget->id,
                'budget_name' => $budget->name,
                'fiscal_year_start' => $budget->fiscal_year_start,
                'fiscal_year_end' => $budget->fiscal_year_end,
                'total_budgeted_amount' => $budget->total_budgeted_amount,
            ]);
        });

        static::updated(function ($budget) {
            if ($budget->wasChanged('approval_status')) {
                audit_log('budget_approval_changed', [
                    'budget_id' => $budget->id,
                    'budget_name' => $budget->name,
                    'old_status' => $budget->getOriginal('approval_status'),
                    'new_status' => $budget->approval_status,
                ]);
            }
        });

        static::deleted(function ($budget) {
            audit_log('budget_deleted', [
                'budget_id' => $budget->id,
                'budget_name' => $budget->name,
            ]);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function budgetLines()
    {
        return $this->hasMany(BudgetLine::class, 'budget_id');
    }

    public function budgetActuals()
    {
        return $this->hasManyThrough(BudgetActual::class, BudgetLine::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function recalculateTotals()
    {
        DB::transaction(function () {
            $this->total_budgeted_amount = $this->budgetLines()->sum('budgeted_amount');
            $this->total_actual_amount = $this->budgetActuals()->sum('actual_amount');

            if ($this->total_budgeted_amount != 0) {
                $this->variance_amount = $this->total_actual_amount - $this->total_budgeted_amount;
                $this->variance_percentage = ($this->variance_amount / $this->total_budgeted_amount) * 100;
            } else {
                $this->variance_amount = 0;
                $this->variance_percentage = 0;
            }

            $this->save();

            audit_log('budget_recalculated', [
                'budget_id' => $this->id,
                'budget_name' => $this->name,
                'total_budgeted' => $this->total_budgeted_amount,
                'total_actual' => $this->total_actual_amount,
                'variance' => $this->variance_amount,
            ]);
        });
    }
}
```

## 3. Fixed Assets Management Module

### 3.1 Database Schema

```sql
-- Fixed Assets Table
CREATE TABLE acct.fixed_assets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id),
    asset_number VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100), -- equipment, furniture, vehicles, buildings, software
    asset_type VARCHAR(100), -- tangible, intangible
    serial_number VARCHAR(100),
    location VARCHAR(255),
    department_id UUID, -- if departmental tracking
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(15,2) NOT NULL,
    current_value DECIMAL(15,2),
    useful_life_years INTEGER NOT NULL,
    useful_life_months INTEGER NOT NULL,
    depreciation_method VARCHAR(50) NOT NULL, -- straight_line, declining_balance, sum_of_years
    depreciation_rate DECIMAL(5,4),
    accumulated_depreciation DECIMAL(15,2) DEFAULT 0,
    salvage_value DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(50) NOT NULL, -- active, disposed, fully_depreciated
    disposal_date DATE NULL,
    disposal_value DECIMAL(15,2) NULL,
    disposal_reason VARCHAR(255),
    created_by_user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Asset Depreciation Table
CREATE TABLE acct.asset_depreciation (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asset_id UUID NOT NULL REFERENCES acct.fixed_assets(id),
    fiscal_year INTEGER NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    opening_book_value DECIMAL(15,2) NOT NULL,
    depreciation_amount DECIMAL(15,2) NOT NULL,
    closing_book_value DECIMAL(15,2) NOT NULL,
    method_used VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3.2 Laravel Models with Depreciation Logic

```php
<?php
// app/Models/FixedAsset.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id', 'asset_number', 'name', 'description', 'category',
        'asset_type', 'serial_number', 'location', 'department_id',
        'purchase_date', 'purchase_cost', 'useful_life_years',
        'useful_life_months', 'depreciation_method', 'depreciation_rate',
        'salvage_value', 'status', 'created_by_user_id'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'disposal_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'current_value' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'disposal_value' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function depreciations()
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function calculateCurrentValue(): float
    {
        $totalDepreciation = $this->depreciations()->sum('depreciation_amount');
        $this->accumulated_depreciation = $totalDepreciation;
        $this->current_value = max(0, $this->purchase_cost - $totalDepreciation);

        if ($this->current_value <= $this->salvage_value) {
            $this->status = 'fully_depreciated';
        }

        $this->save();

        return $this->current_value;
    }

    public function calculateAnnualDepreciation(): float
    {
        $depreciableAmount = $this->purchase_cost - $this->salvage_value;

        return match($this->depreciation_method) {
            'straight_line' => $depreciableAmount / ($this->useful_life_years + ($this->useful_life_months / 12)),
            'declining_balance' => $depreciableAmount * ($this->depreciation_rate / 100),
            default => 0,
        };
    }

    public function isFullyDepreciated(): bool
    {
        return $this->accumulated_depreciation >= ($this->purchase_cost - $this->salvage_value);
    }
}
```

## 4. Inventory Management UI (Complete Product Model Integration)

### 4.1 Product Controller

```php
<?php
// app/Http/Controllers/InventoryManagement/ProductController.php
namespace App\Http\Controllers\InventoryManagement;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('company_id', Auth::user()->current_company_id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->search($search);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'low_stock') {
                $query->whereRaw('stock_quantity <= min_stock_level');
            } else {
                $query->where('is_active', $status === 'active');
            }
        }

        $products = $query->orderBy('name')->paginate(25);

        return Inertia::render('InventoryManagement/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('InventoryManagement/Products/Create', [
            'accounts' => $this->getAccountsList(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_code' => 'required|string|max:50|unique:acct.products',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'unit_price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'account_code' => 'required|string|max:50',
            'inventory_tracking' => 'boolean',
            'stock_quantity' => 'required|numeric|min:0',
            'min_stock_level' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        Product::create([
            'company_id' => Auth::user()->current_company_id,
            'created_by_user_id' => Auth::id(),
            ...$validated,
        ]);

        return redirect()->route('inventory.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function adjustStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'adjustment_type' => 'required|in:increase,decrease,set',
            'quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $originalQuantity = $product->stock_quantity;

        switch ($validated['adjustment_type']) {
            case 'increase':
                $product->increaseStock($validated['quantity']);
                break;
            case 'decrease':
                $product->decreaseStock($validated['quantity']);
                break;
            case 'set':
                $product->stock_quantity = $validated['quantity'];
                $product->save();
                break;
        }

        // Log the adjustment
        ProductStockAdjustment::create([
            'product_id' => $product->id,
            'original_quantity' => $originalQuantity,
            'new_quantity' => $product->stock_quantity,
            'adjustment_type' => $validated['adjustment_type'],
            'quantity_change' => $validated['quantity'],
            'reason' => $validated['reason'],
            'adjusted_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Stock adjusted successfully.');
    }

    private function getAccountsList(): array
    {
        return Account::where('company_id', Auth::user()->current_company_id)
            ->where('account_type', 'asset')
            ->where('account_subtype', 'inventory')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();
    }
}
```

### 4.2 Product Stock Adjustments Table

```sql
CREATE TABLE acct.product_stock_adjustments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES acct.products(id),
    original_quantity DECIMAL(10,4) NOT NULL,
    new_quantity DECIMAL(10,4) NOT NULL,
    adjustment_type VARCHAR(20) NOT NULL, -- increase, decrease, set
    quantity_change DECIMAL(10,4) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    adjusted_by_user_id UUID REFERENCES auth.users(id),
    adjustment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 5. Command Palette Implementation

### 5.1 Vue Component

```vue
<!-- resources/js/Components/CommandPalette.vue -->
<template>
  <div class="command-palette-overlay" v-if="isOpen" @click="close">
    <div class="command-palette" @click.stop>
      <div class="search-container">
        <input
          ref="searchInput"
          v-model="searchQuery"
          @input="filterCommands"
          @keydown="handleKeyDown"
          class="search-input"
          placeholder="Type a command or search..."
        />
        <button @click="close" class="close-button">×</button>
      </div>

      <div class="results-container">
        <div v-if="filteredCommands.length === 0" class="no-results">
          No commands found
        </div>

        <div v-for="(category, index) in categorizedCommands" :key="category.name" class="category">
          <div class="category-title">{{ category.name }}</div>
          <div
            v-for="command in category.commands"
            :key="command.action"
            class="command-item"
            :class="{ active: selectedIndex === command.index }"
            @click="executeCommand(command)"
            @mouseenter="selectedIndex = command.index"
          >
            <div class="command-icon">{{ command.icon }}</div>
            <div class="command-details">
              <div class="command-title">{{ command.title }}</div>
              <div class="command-description">{{ command.description }}</div>
            </div>
            <div class="command-shortcut">{{ command.shortcut }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'

export default {
  name: 'CommandPalette',
  setup() {
    const isOpen = ref(false)
    const searchQuery = ref('')
    const selectedIndex = ref(0)
    const searchInput = ref(null)

    const commands = [
      {
        category: 'Navigation',
        commands: [
          { action: 'dashboard', title: 'Dashboard', description: 'Go to dashboard', icon: '🏠', shortcut: 'Ctrl+D' },
          { action: 'invoices', title: 'Invoices', description: 'Manage invoices', icon: '📄', shortcut: 'Ctrl+I' },
          { action: 'customers', title: 'Customers', description: 'Manage customers', icon: '👥', shortcut: 'Ctrl+C' },
          { action: 'reports', title: 'Reports', description: 'View financial reports', icon: '📊', shortcut: 'Ctrl+R' },
        ]
      },
      {
        category: 'Quick Actions',
        commands: [
          { action: 'new_invoice', title: 'New Invoice', description: 'Create a new invoice', icon: '➕', shortcut: 'Alt+I' },
          { action: 'new_customer', title: 'New Customer', description: 'Add a new customer', icon: '👤', shortcut: 'Alt+C' },
          { action: 'new_vendor', title: 'New Vendor', description: 'Add a new vendor', icon: '🏢', shortcut: 'Alt+V' },
        ]
      },
      // Add more categories based on your application
    ]

    const filteredCommands = computed(() => {
      if (!searchQuery.value) {
        return commands
      }

      const query = searchQuery.value.toLowerCase()
      return commands.map(category => ({
        ...category,
        commands: category.commands.filter(command =>
          command.title.toLowerCase().includes(query) ||
          command.description.toLowerCase().includes(query) ||
          command.action.toLowerCase().includes(query)
        )
      })).filter(category => category.commands.length > 0)
    })

    const categorizedCommands = computed(() => {
      let index = 0
      return filteredCommands.value.map(category => ({
        ...category,
        commands: category.commands.map(command => ({
          ...command,
          index: index++
        }))
      }))
    })

    const open = () => {
      isOpen.value = true
      searchQuery.value = ''
      selectedIndex.value = 0
      nextTick(() => {
        searchInput.value?.focus()
      })
    }

    const close = () => {
      isOpen.value = false
      searchQuery.value = ''
    }

    const executeCommand = (command) => {
      router.visit(route(command.action))
      close()
    }

    const handleKeyDown = (event) => {
      switch (event.key) {
        case 'Escape':
          close()
          break
        case 'ArrowDown':
          event.preventDefault()
          selectedIndex.value = Math.min(selectedIndex.value + 1, getAllCommands().length - 1)
          break
        case 'ArrowUp':
          event.preventDefault()
          selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
          break
        case 'Enter':
          const allCommands = getAllCommands()
          if (selectedIndex.value < allCommands.length) {
            executeCommand(allCommands[selectedIndex.value])
          }
          break
      }
    }

    const getAllCommands = () => {
      return categorizedCommands.value.flatMap(category => category.commands)
    }

    const setupGlobalKeyboardShortcuts = () => {
      document.addEventListener('keydown', (event) => {
        if (event.ctrlKey && event.key === 'k') {
          event.preventDefault()
          isOpen.value ? close() : open()
        }
      })
    }

    onMounted(() => {
      setupGlobalKeyboardShortcuts()
    })

    return {
      isOpen,
      searchQuery,
      selectedIndex,
      searchInput,
      filteredCommands,
      categorizedCommands,
      open,
      close,
      executeCommand,
      handleKeyDown,
    }
  }
}
</script>

<style scoped>
.command-palette-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 15vh;
}

.command-palette {
  background: white;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  width: 600px;
  max-height: 400px;
  overflow: hidden;
}

.search-container {
  display: flex;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 16px;
  padding: 8px;
}

.close-button {
  background: none;
  border: none;
  font-size: 20px;
  cursor: pointer;
  color: #6b7280;
  padding: 4px 8px;
}

.results-container {
  max-height: 350px;
  overflow-y: auto;
}

.category-title {
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
}

.command-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  cursor: pointer;
  border-left: 3px solid transparent;
}

.command-item:hover,
.command-item.active {
  background: #f3f4f6;
  border-left-color: #3b82f6;
}

.command-icon {
  margin-right: 12px;
  font-size: 18px;
}

.command-details {
  flex: 1;
}

.command-title {
  font-weight: 500;
  color: #111827;
}

.command-description {
  font-size: 14px;
  color: #6b7280;
}

.command-shortcut {
  font-size: 12px;
  color: #9ca3af;
  background: #f3f4f6;
  padding: 2px 6px;
  border-radius: 4px;
}

.no-results {
  padding: 32px 16px;
  text-align: center;
  color: #6b7280;
}
</style>
```

## Implementation Priority

### Phase 4.1 - High Priority (Core Features)
1. **Tax Management Module** - Critical for business compliance
2. **Budget Management Module** - Essential for financial planning
3. **Product Inventory UI** - Complete the existing backend foundation

### Phase 4.2 - Medium Priority
4. **Fixed Assets Management** - Important for asset tracking
5. **Command Palette** - Improves user experience

### Phase 4.3 - Integration & Testing
6. **Route Integration** - Add all new routes to main navigation
7. **Permission Setup** - Create permissions for all new modules
8. **Testing** - Comprehensive testing of all new features

## Constitutional Compliance Requirements

### ✅ Mandatory Implementation Standards

**Schema Compliance:**
- All tables MUST be in `acct` schema (per Domain Separation principle)
- UUID primary keys with `gen_random_uuid()`
- Foreign key constraints with proper references
- `company_id` scoping for tenant isolation
- `deleted_at TIMESTAMP NULL` for soft deletes
- Positive amount `CHECK` constraints on financial columns

**Security Requirements:**
- Row Level Security (RLS) policies using `current_setting('app.current_company_id')`
- `audit_log()` integration for financial events
- Company-scoped model queries (`scopeForCompany`)
- Transaction boundaries for financial operations

**Frontend Patterns:**
- PrimeVue components (consistent with existing UI)
- Debounced search to prevent excessive API calls
- Null-safe data handling across all components
- Component-based architecture (primary Index.vue + supporting components)
- Single source of truth for state management

**Testing Requirements:**
- Feature tests for all migrations and handlers
- Permission-based access control testing
- Audit trail verification
- Database constraint validation

## Testing Framework (Required)

```php
// Example Feature Test Template
<?php
// tests/Feature/Phase4/TaxManagementTest.php
namespace Tests\Feature\Phase4;

use App\Models\TaxAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use function audit_log;

class TaxManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->createUserWithCompany());
    }

    public function test_can_create_tax_agency()
    {
        $response = $this->post('/tax-management/agencies', [
            'name' => 'IRS',
            'tax_type' => 'income_tax',
            'filing_frequency' => 'quarterly',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('acct.tax_agencies', ['name' => 'IRS']);
    }

    public function test_tax_agency_creation_logs_audit_entry()
    {
        $agency = TaxAgency::factory()->create();

        $this->assertDatabaseHas('audit.entries', [
            'event_type' => 'tax_agency_created',
            'entity_id' => $agency->id,
        ]);
    }

    public function test_rls_policy_prevents_cross_company_access()
    {
        // Test that users from Company A cannot access Company B's tax data
        $otherCompanyTaxAgency = TaxAgency::factory()
            ->create(['company_id' => $this->createCompany()->id]);

        $response = $this->get("/tax-management/agencies/{$otherCompanyTaxAgency->id}");
        $response->assertForbidden();
    }
}
```

## Migration Template (Required Pattern)

```php
<?php
// database/migrations/2025_11_12_000001_create_tax_agencies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.tax_agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('name');
            // ... other columns
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users');

            $table->index(['company_id', 'name']);
        });

        Schema::table('acct.tax_agencies', function (Blueprint $table) {
            $table->check('rate >= 0');
        });

        DB::statement('ALTER TABLE acct.tax_agencies ENABLE ROW LEVEL SECURITY');
        DB::statement('CREATE POLICY tax_agencies_company_policy ON acct.tax_agencies
            USING (company_id = current_setting(\'app.current_company_id\')::UUID)');
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.tax_agencies');
    }
};
```

## Required Permissions (Add to database/seeders)

```php
// Add to CommandSeeder or relevant seeder
[
    'name' => 'tax.view',
    'guard_name' => 'web',
    'description' => 'View tax management data',
],
[
    'name' => 'tax.create',
    'guard_name' => 'web',
    'description' => 'Create tax agencies and rates',
],
[
    'name' => 'budget.view',
    'guard_name' => 'web',
    'description' => 'View budget data',
],
[
    'name' => 'budget.create',
    'guard_name' => 'web',
    'description' => 'Create and manage budgets',
],
// ... add all required permissions
```

## Database Migration Scripts

Create individual migration files for each table (following constitutional patterns):

```bash
php artisan make:migration create_tax_agencies_table --path=database/migrations
php artisan make:migration create_tax_rates_table --path=database/migrations
php artisan make:migration create_tax_returns_table --path=database/migrations
php artisan make:migration create_tax_payments_table --path=database/migrations
php artisan make:migration create_budgets_table --path=database/migrations
php artisan make:migration create_budget_lines_table --path=database/migrations
php artisan make:migration create_budget_actuals_table --path=database/migrations
php artisan make:migration create_fixed_assets_table --path=database/migrations
php artisan make:migration create_asset_depreciation_table --path=database/migrations
php artisan make:migration create_product_stock_adjustments_table --path=database/migrations
```

This implementation guide provides everything needed to complete Phase 4 with enterprise-grade modules that fully comply with the Haasib Architecture Constitution and all organizational requirements.