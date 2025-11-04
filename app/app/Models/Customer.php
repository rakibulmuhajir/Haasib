<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.customers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'id',
        'country_data',
        'currency_data',
        'outstanding_balance',
        'risk_level',
    ];

    /**
     * Get the id attribute (alias for customer_id).
     */
    public function getIdAttribute(): string
    {
        return $this->attributes['id'] ?? '';
    }

    protected $fillable = [
        'id',
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
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate UUID for id if not set (database will auto-generate)
            if (! isset($model->id)) {
                $model->id = (string) Str::uuid();
            }

            // Generate customer number if not set
            if (! isset($model->customer_number)) {
                $model->customer_number = $model->generateCustomerNumber();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country_relation(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', 'id');
    }

    public function getCountryDataAttribute()
    {
        if ($this->country) {
            // For table display, we only need code and name
            $country = $this->country->only(['code', 'name']);
            return $country;
        }

        return null;
    }

    public function getCurrencyDataAttribute()
    {
        // If the relationship is loaded, use it
        if ($this->relationLoaded('currency') && $this->currency) {
            return $this->currency->code;
        }

        // Otherwise, query the database
        if ($this->currency_id) {
            $currency = Currency::find($this->currency_id, ['id', 'code']);

            return $currency ? $currency->code : null;
        }

        return null;
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return $this->getOutstandingBalance();
    }

    public function getRiskLevelAttribute(): string
    {
        return $this->getRiskLevel();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id', 'id');
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class, 'customer_id', 'id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id', 'id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class, 'customer_id', 'id')->where('is_primary', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    public function generateCustomerNumber(): string
    {
        $company = $this->company;
        $year = now()->year;

        $prefix = $company->settings['customer_prefix'] ?? 'CUST';
        $pattern = $company->settings['customer_number_pattern'] ?? '{prefix}-{year}-{sequence:4}';

        // Get all customers for this company in the current year and process in PHP
        $customers = static::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->get(['customer_number']);

        $maxSequence = 0;
        foreach ($customers as $customer) {
            // Extract the numeric part from the customer number
            if (preg_match('/(\d+)$/', $customer->customer_number, $matches)) {
                $sequence = (int) $matches[1];
                $maxSequence = max($maxSequence, $sequence);
            }
        }

        $sequence = $maxSequence + 1;

        return str_replace(
            ['{prefix}', '{year}', '{sequence:4}', '{sequence:5}', '{sequence:6}'],
            [$prefix, $year, str_pad($sequence, 4, '0', STR_PAD_LEFT), str_pad($sequence, 5, '0', STR_PAD_LEFT), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $pattern
        );
    }

    public function getOutstandingBalance(): float
    {
        return $this->accountsReceivable()->where('amount_due', '>', 0)->sum('amount_due');
    }

    public function getAvailableCredit(): float
    {
        return max(0, $this->credit_limit - $this->getOutstandingBalance());
    }

    public function isOverCreditLimit(): bool
    {
        return $this->getOutstandingBalance() > $this->credit_limit;
    }

    public function getOverdueInvoicesCount(): int
    {
        return $this->invoices()
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->where('due_date', '<', now())
            ->count();
    }

    public function getAveragePaymentDays(): int
    {
        $paidInvoices = $this->invoices()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get();

        if ($paidInvoices->isEmpty()) {
            return 0;
        }

        $totalDays = 0;
        $count = 0;

        foreach ($paidInvoices as $invoice) {
            if ($invoice->invoice_date && $invoice->paid_at) {
                $totalDays += \Carbon\Carbon::parse($invoice->invoice_date)->diffInDays($invoice->paid_at);
                $count++;
            }
        }

        return $count > 0 ? round($totalDays / $count) : 0;
    }

    public function getRiskLevel(): string
    {
        $overdueCount = $this->getOverdueInvoicesCount();
        $overCredit = $this->isOverCreditLimit();
        $outstandingBalance = $this->getOutstandingBalance();

        if ($overCredit && $overdueCount > 3) {
            return 'critical';
        }

        if ($overdueCount > 5 || ($overCredit && $outstandingBalance > 10000)) {
            return 'high';
        }

        if ($overdueCount > 2 || $overCredit) {
            return 'medium';
        }

        return 'low';
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->customer_number;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\CustomerFactory::new();
    }
}
