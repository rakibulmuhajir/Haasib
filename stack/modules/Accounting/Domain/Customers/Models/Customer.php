<?php

namespace Modules\Accounting\Domain\Customers\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'acct.customers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'website',
        'notes',
        'credit_limit',
        'currency',
        'status',
        'opening_balance',
        'opening_balance_date',
        'metadata',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'active',
        'currency' => 'USD',
    ];

    /**
     * Get the contacts for the customer.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /**
     * Get the addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Get the credit limits for the customer.
     */
    public function creditLimits(): HasMany
    {
        return $this->hasMany(CustomerCreditLimit::class);
    }

    /**
     * Get the statements for the customer.
     */
    public function statements(): HasMany
    {
        return $this->hasMany(CustomerStatement::class);
    }

    /**
     * Get the aging snapshots for the customer.
     */
    public function agingSnapshots(): HasMany
    {
        return $this->hasMany(CustomerAgingSnapshot::class);
    }

    /**
     * Get the communications for the customer.
     */
    public function communications(): HasMany
    {
        return $this->hasMany(CustomerCommunication::class);
    }

    /**
     * Get the groups for the customer.
     */
    public function groups()
    {
        return $this->belongsToMany(CustomerGroup::class, 'acct.customer_group_members');
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
                ->orWhere('email', 'ILIKE', "%{$term}%")
                ->orWhere('customer_number', 'ILIKE', "%{$term}%");
        });
    }

    /**
     * Get outstanding balance for the customer
     */
    public function getOutstandingBalance(): float
    {
        // Since we don't have the invoices table in the acct schema yet,
        // this returns 0 for now
        // TODO: Implement once invoices table is properly set up
        return 0.0;
    }

    /**
     * Get available credit for the customer
     */
    public function getAvailableCredit(): float
    {
        $outstandingBalance = $this->getOutstandingBalance();
        $creditLimit = $this->credit_limit ?? 0;

        return max(0, $creditLimit - $outstandingBalance);
    }

    /**
     * Get risk level for the customer
     */
    public function getRiskLevel(): string
    {
        $outstandingBalance = $this->getOutstandingBalance();
        $creditLimit = $this->credit_limit ?? 0;

        if ($creditLimit == 0) {
            return 'low';
        }

        $usageRatio = $outstandingBalance / $creditLimit;

        if ($usageRatio >= 0.9) {
            return 'high';
        } elseif ($usageRatio >= 0.7) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get average payment days for the customer
     */
    public function getAveragePaymentDays(): int
    {
        // TODO: Implement when payment history is available
        // For now, return default payment terms or 30 days
        return 30;
    }

    /**
     * Get count of overdue invoices for the customer
     */
    public function getOverdueInvoicesCount(): int
    {
        // TODO: Implement when invoices table is properly set up
        return 0;
    }
}