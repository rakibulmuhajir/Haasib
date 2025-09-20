<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $primaryKey = 'customer_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['id'];

    /**
     * Get the id attribute (alias for customer_id).
     */
    public function getIdAttribute(): string
    {
        return $this->attributes['customer_id'] ?? '';
    }

    protected $fillable = [
        'customer_id',
        'id',
        'company_id',
        'name',
        'email',
        'phone',
        'tax_number',
        'billing_address',
        'shipping_address',
        'currency_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'string',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'currency_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
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
            // Generate UUID for customer_id if not set
            if (! isset($model->customer_id)) {
                $model->customer_id = (string) Str::uuid();
            }
            // Also set id attribute for compatibility
            if (! isset($model->id)) {
                $model->id = $model->customer_id;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'customer_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'customer_id', 'customer_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class, 'customer_id', 'customer_id')->where('is_primary', true);
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

        $latestCustomer = static::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->orderByRaw('CAST(SUBSTRING(customer_number FROM GREATEST(POSITION("-" IN customer_number), POSITION(" " IN customer_number)) + 1) AS UNSIGNED) DESC')
            ->first();

        $sequence = $latestCustomer ? ((int) preg_replace('/.*?(\d+)$/', '$1', $latestCustomer->customer_number)) + 1 : 1;

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
}
