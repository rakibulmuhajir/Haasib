# Model Remediation Prompt

## Task: Fix Model Constitutional Violations

You are a **Laravel Eloquent Expert** specialized in model remediation for multi-tenant UUID-based systems.

## CURRENT VIOLATIONS TO FIX

### **Common Non-Compliant Patterns Found**

#### **1. Missing UUID Traits (CRITICAL)**
```php
// BEFORE (VIOLATION)
class Customer extends Model
{
    protected $fillable = ['name', 'email'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

// AFTER (CONSTITUTIONAL)
class Customer extends BaseModel
{
    use HasUuids, BelongsToCompany, SoftDeletes, AuditLog;

    protected $table = 'acct.customers';

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'email',
        'tax_id',
        'status',
        'credit_limit',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'status' => CustomerStatus::class,
        'deleted_at' => 'datetime',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;
}
```

#### **2. Incorrect Table Schema (CRITICAL)**
```php
// BEFORE (VIOLATION)
class Customer extends Model
{
    // ❌ No table specification - assumes 'customers' in public schema
}

// AFTER (CONSTITUTIONAL)
class Customer extends BaseModel
{
    // ✅ Correct schema and table name
    protected $table = 'acct.customers';
}
```

#### **3. Missing Company Scoping (CRITICAL)**
```php
// BEFORE (VIOLATION)
class Customer extends Model
{
    public static function active()
    {
        // ❌ No company scoping - returns all customers across all companies
        return static::where('status', 'active')->get();
    }
}

// AFTER (CONSTITUTIONAL)
class Customer extends BaseModel
{
    public static function active(?string $companyId = null): Collection
    {
        // ✅ Proper company scoping via BelongsToCompany trait
        $query = static::where('status', CustomerStatus::ACTIVE);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->get();
    }

    // Or use the trait's automatic scoping
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::ACTIVE);
    }
}
```

#### **4. Incorrect Relationship Definitions (CRITICAL)**
```php
// BEFORE (VIOLATION)
class Customer extends Model
{
    public function user() // ❌ Vague relationship name
    {
        return $this->belongsTo(User::class); // ❌ No return type
    }

    public function invoices() // ❌ No proper typing
    {
        return $this->hasMany(Invoice::class);
    }
}

// AFTER (CONSTITUTIONAL)
class Customer extends BaseModel
{
    // Relationships with proper typing and clarity
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }
}
```

#### **5. Missing Business Logic Methods (CRITICAL)**
```php
// BEFORE (VIOLATION)
class Customer extends Model
{
    // ❌ No business logic methods
    // Developers calling $customer->status = 'paid' directly
}

// AFTER (CONSTITUTIONAL)
class Customer extends BaseModel
{
    // Business logic methods with clear return types and validation
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::SUSPENDED;
    }

    public function canBeDeleted(): bool
    {
        return $this->invoices()->count() === 0;
    }

    public function canPlaceInvoice(float $amount): bool
    {
        return $this->isActive() && ($this->getOutstandingBalance() + $amount) <= $this->credit_limit;
    }

    public function getOutstandingBalance(): float
    {
        return $this->invoices()
            ->where('status', '!=', InvoiceStatus::PAID)
            ->sum('balance_due');
    }

    public function getAvailableCredit(): float
    {
        return $this->credit_limit - $this->getOutstandingBalance();
    }

    public function getAgingBalance(): array
    {
        return [
            'current' => $this->getBalanceByAge(0, 30),
            'days_31_60' => $this->getBalanceByAge(31, 60),
            'days_61_90' => $this->getBalanceByAge(61, 90),
            'over_90' => $this->getBalanceByAge(91, 999),
        ];
    }

    private function getBalanceByAge(int $minDays, int $maxDays): float
    {
        $cutoffDate = now()->subDays($maxDays);
        $startDate = $maxDays === 999 ? now()->subDays($minDays) : now()->subDays($minDays);

        return $this->invoices()
            ->where('status', '!=', InvoiceStatus::PAID)
            ->whereBetween('due_date', [$startDate, $cutoffDate])
            ->sum('balance_due');
    }

    public function getCustomerNumberAttribute(): string
    {
        // Generate customer number if not set
        if (!$this->attributes['customer_number']) {
            $this->attributes['customer_number'] = $this->generateCustomerNumber();
            $this->save();
        }

        return $this->attributes['customer_number'];
    }

    private function generateCustomerNumber(): string
    {
        $maxNumber = static::where('company_id', $this->company_id)
            ->whereNotNull('customer_number')
            ->max('customer_number');

        $nextNumber = (int)str_replace('CUST-', '', $maxNumber ?? 'CUST-00000') + 1;

        return 'CUST-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
```

## COMPLETE MODEL TEMPLATE

```php
<?php

namespace App\Models\Acct;

use App\Models\BaseModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasUuids;
use App\Traits\SoftDeletes;
use App\Traits\AuditLog;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class Customer extends BaseModel
{
    use HasUuids, BelongsToCompany, SoftDeletes, AuditLog;

    protected $table = 'acct.customers';

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'email',
        'tax_id',
        'status',
        'credit_limit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'status' => CustomerStatus::class,
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'outstanding_balance',
        'available_credit',
        'is_active',
        'aging_balance',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === RELATIONSHIPS ===

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(CustomerStatement::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CustomerCommunication::class);
    }

    // === SCOPES ===

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::ACTIVE);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::SUSPENDED);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::INACTIVE);
    }

    public function scopeWithOutstandingBalance(Builder $query): Builder
    {
        return $query->whereHas('invoices', function (Builder $q) {
            $q->where('status', '!=', InvoiceStatus::PAID)
              ->where('balance_due', '>', 0);
        });
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    // === BUSINESS LOGIC METHODS ===

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::SUSPENDED;
    }

    public function isInactive(): bool
    {
        return $this->status === CustomerStatus::INACTIVE;
    }

    public function canBeDeleted(): bool
    {
        return $this->invoices()->count() === 0;
    }

    public function canBeSuspended(): bool
    {
        return $this->isActive() && $this->getOutstandingBalance() === 0;
    }

    public function canPlaceInvoice(float $amount): bool
    {
        return $this->isActive() && ($this->getOutstandingBalance() + $amount) <= $this->credit_limit;
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return $this->getOutstandingBalance();
    }

    public function getOutstandingBalance(): float
    {
        return (float) $this->invoices()
            ->where('status', '!=', InvoiceStatus::PAID)
            ->sum('balance_due');
    }

    public function getAvailableCreditAttribute(): float
    {
        return $this->getAvailableCredit();
    }

    public function getAvailableCredit(): float
    {
        return max(0, $this->credit_limit - $this->getOutstandingBalance());
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function getAgingBalanceAttribute(): array
    {
        return $this->getAgingBalance();
    }

    public function getAgingBalance(): array
    {
        return [
            'current' => $this->getBalanceByAge(0, 30),
            'days_31_60' => $this->getBalanceByAge(31, 60),
            'days_61_90' => $this->getBalanceByAge(61, 90),
            'over_90' => $this->getBalanceByAge(91, 999),
        ];
    }

    public function getTotalInvoicesCount(): int
    {
        return $this->invoices()->count();
    }

    public function getPaidInvoicesCount(): int
    {
        return $this->invoices()->where('status', InvoiceStatus::PAID)->count();
    }

    public function getUnpaidInvoicesCount(): int
    {
        return $this->invoices()->where('status', '!=', InvoiceStatus::PAID)->count();
    }

    public function getLastInvoiceDate(): ?\Carbon\Carbon
    {
        return $this->invoices()->max('created_at');
    }

    public function getAverageInvoiceAmount(): float
    {
        return (float) $this->invoices()->avg('total_amount') ?? 0;
    }

    // === MUTATORS & ACCESSORS ===

    public function getCustomerNumberAttribute(): string
    {
        if (!$this->attributes['customer_number']) {
            $this->attributes['customer_number'] = $this->generateCustomerNumber();
            $this->save();
        }

        return $this->attributes['customer_number'];
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    public function setTaxIdAttribute(?string $value): void
    {
        $this->attributes['tax_id'] = $value ? strtoupper(trim($value)) : null;
    }

    // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (!$customer->customer_number) {
                $customer->customer_number = $customer->generateCustomerNumber();
            }

            if (auth()->check()) {
                $customer->created_by = auth()->id();
                $customer->updated_by = auth()->id();
            }
        });

        static::updating(function (Customer $customer) {
            if (auth()->check()) {
                $customer->updated_by = auth()->id();
            }
        });

        static::deleting(function (Customer $customer) {
            if ($customer->canBeDeleted() === false) {
                throw new CustomerHasInvoicesException(
                    'Cannot delete customer with existing invoices'
                );
            }
        });
    }

    // === PRIVATE METHODS ===

    private function generateCustomerNumber(): string
    {
        $maxNumber = static::where('company_id', $this->company_id)
            ->whereNotNull('customer_number')
            ->max('customer_number');

        $nextNumber = (int)str_replace('CUST-', '', $maxNumber ?? 'CUST-00000') + 1;

        return 'CUST-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    private function getBalanceByAge(int $minDays, int $maxDays): float
    {
        $query = $this->invoices()
            ->where('status', '!=', InvoiceStatus::PAID);

        if ($maxDays === 999) {
            $query->where('due_date', '<', now()->subDays($minDays));
        } else {
            $query->whereBetween('due_date', [
                now()->subDays($maxDays),
                now()->subDays($minDays)
            ]);
        }

        return (float) $query->sum('balance_due');
    }

    // === QUERY SCOPES ===

    public function scopeByCustomerNumber(Builder $query, string $customerNumber): Builder
    {
        return $query->where('customer_number', $customerNumber);
    }

    public function scopeByTaxId(Builder $query, string $taxId): Builder
    {
        return $query->where('tax_id', $taxId);
    }

    public function scopeHasCreditLimit(Builder $query): Builder
    {
        return $query->where('credit_limit', '>', 0);
    }

    public function scopeOverCreditLimit(Builder $query): Builder
    {
        return $query->whereRaw('(SELECT COALESCE(SUM(balance_due), 0) FROM acct.invoices WHERE customer_id = acct.customers.id AND status != ?) > credit_limit', [InvoiceStatus::PAID]);
    }
}
```

## REQUIRED ENUMS

### **CustomerStatus.php**
```php
<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::SUSPENDED => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::ACTIVE => 'pi pi-check-circle',
            self::INACTIVE => 'pi pi-pause-circle',
            self::SUSPENDED => 'pi pi-times-circle',
        };
    }

    public static function getAll(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->getLabel(),
            self::INACTIVE->value => self::INACTIVE->getLabel(),
            self::SUSPENDED->value => self::SUSPENDED->getLabel(),
        ];
    }
}
```

### **CustomerHasInvoicesException.php**
```php
<?php

namespace App\Exceptions;

use Exception;

class CustomerHasInvoicesException extends Exception
{
    protected $message = 'Customer has existing invoices and cannot be deleted';
}
```

## CHECKLIST FOR EVERY MODEL

### **✅ Must Include:**
- [ ] Extend BaseModel (or appropriate base)
- [ ] Use HasUuids, BelongsToCompany, SoftDeletes, AuditLog traits
- [ ] Correct table name with schema prefix
- [ ] UUID configuration (`$keyType = 'string', $incrementing = false`)
- [ ] Proper fillable array with explicit fields
- [ ] Comprehensive casts (decimal:2, datetime, enums)
- [ ] Typed relationships with proper return types
- [ ] Business logic methods with clear boolean returns
- [ ] Proper mutators/accessors for data formatting
- [ ] Model events for audit trails
- [ ] Query scopes for common filters
- [ ] Validation methods (canBeDeleted, canPlaceInvoice, etc.)
- [ ] Calculated attributes (outstanding_balance, available_credit)

### **❌ Must NOT Include:**
- [ ] Direct auth() or request() calls
- [ ] Hard-coded magic strings
- [ ] Unvalidated database queries
- [ ] Missing return types on relationships
- [ ] Business logic that belongs in services
- [ ] Direct database queries in model methods

## VALIDATION COMMANDS

```bash
# Test model creation
php artisan tinker
> $customer = Customer::create(['name' => 'Test Customer']);
> echo $customer->customer_number; // Should generate auto
> echo $customer->isActive(); // Should return true

# Test RLS scoping
php artisan tinker
> $customers = Customer::all(); // Should only return current company's customers

# Test business logic
php artisan tinker
> $customer->canPlaceInvoice(100); // Should return based on credit limit
> $customer->getOutstandingBalance(); // Should return calculated balance
```

Apply this template to ALL models in your codebase.