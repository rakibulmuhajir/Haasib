<?php

namespace Modules\Acct\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'acct.customers';

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
        'company_id' => 'string',
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
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the payments for the customer.
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
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
