<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
        'payment_terms',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
            'company_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the customer.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the customer.
     */
    public function creator(): BelongsTo
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
     * Get the payments from the customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search customers by name or email.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('customer_number', 'ilike', "%{$term}%");
        });
    }

    /**
     * Get the total outstanding balance for the customer.
     */
    public function getOutstandingBalance(): float
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->sum('balance_due');
    }

    /**
     * Get the total paid amount for the customer.
     */
    public function getTotalPaid(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Get the total invoiced amount for the customer.
     */
    public function getTotalInvoiced(): float
    {
        return $this->invoices()->sum('total_amount');
    }

    /**
     * Check if customer has overdue invoices.
     */
    public function hasOverdueInvoices(): bool
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->exists();
    }

    /**
     * Get overdue invoices.
     */
    public function getOverdueInvoices(): HasMany
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now());
    }

    /**
     * Generate a unique customer number.
     */
    public static function generateCustomerNumber(string $companyId): string
    {
        $prefix = 'CUST';
        $sequence = static::where('company_id', $companyId)
            ->withTrashed()
            ->count() + 1;

        return "{$prefix}-{$companyId}-{$sequence}";
    }

    /**
     * Get customer's display name with number.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->customer_number} - {$this->name}";
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\Invoicing\CustomerFactory::new();
    }
}
