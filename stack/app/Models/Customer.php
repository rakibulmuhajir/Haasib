<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoicing.customers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'legal_name',
        'status',
        'email',
        'phone',
        'default_currency',
        'payment_terms',
        'credit_limit',
        'credit_limit_effective_at',
        'tax_id',
        'website',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_limit_effective_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the company that owns the customer.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the customer.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the contacts for the customer.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerContact::class);
    }

    /**
     * Get the addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerAddress::class);
    }

    /**
     * Get the credit limits for the customer.
     */
    public function creditLimits(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit::class);
    }

    /**
     * Get the statements for the customer.
     */
    public function statements(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerStatement::class);
    }

    /**
     * Get the aging snapshots for the customer.
     */
    public function agingSnapshots(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerAgingSnapshot::class);
    }

    /**
     * Get the communications for the customer.
     */
    public function communications(): HasMany
    {
        return $this->hasMany(\Modules\Accounting\Domain\Customers\Models\CustomerCommunication::class);
    }

    /**
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the payments for the customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the groups for the customer.
     */
    public function groups()
    {
        return $this->belongsToMany(\Modules\Accounting\Domain\Customers\Models\CustomerGroup::class, 'invoicing.customer_group_members');
    }

    /**
     * Scope to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to only include customers with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to search customers by name or email.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ILIKE', "%{$term}%")
                ->orWhere('legal_name', 'ILIKE', "%{$term}%")
                ->orWhere('email', 'ILIKE', "%{$term}%")
                ->orWhere('customer_number', 'ILIKE', "%{$term}%");
        });
    }

    /**
     * Get the current credit limit (latest approved).
     */
    public function getCurrentCreditLimitAttribute()
    {
        return $this->creditLimits()
            ->where('status', 'approved')
            ->where('effective_at', '<=', now())
            ->orderBy('effective_at', 'desc')
            ->first();
    }

    /**
     * Get the current balance (sum of unpaid invoices).
     */
    public function getCurrentBalanceAttribute()
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->sum('balance_due');
    }

    /**
     * Get available credit (credit limit minus current balance).
     */
    public function getAvailableCreditAttribute()
    {
        $creditLimit = $this->current_credit_limit?->limit_amount ?? 0;
        $currentBalance = $this->current_balance;

        return max(0, $creditLimit - $currentBalance);
    }
}
