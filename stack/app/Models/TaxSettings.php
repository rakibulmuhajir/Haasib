<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSettings extends Model
{
    use HasFactory;

    protected $table = 'acct.tax_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'tax_inclusive_pricing',
        'round_tax_per_line',
        'allow_compound_tax',
        'rounding_precision',
        'tax_registration_number',
        'vat_number',
        'tax_country_code',
        'default_reporting_frequency',
        'auto_file_tax_returns',
        'tax_year_end_month',
        'tax_year_end_day',
        'calculate_sales_tax',
        'charge_tax_on_shipping',
        'tax_exempt_customers',
        'default_sales_tax_rate_id',
        'calculate_purchase_tax',
        'track_input_tax',
        'default_purchase_tax_rate_id',
        'auto_calculate_tax',
        'validate_tax_rates',
        'track_tax_by_jurisdiction',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'default_sales_tax_rate_id' => 'string',
        'default_purchase_tax_rate_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'tax_inclusive_pricing' => 'boolean',
        'round_tax_per_line' => 'boolean',
        'allow_compound_tax' => 'boolean',
        'auto_file_tax_returns' => 'boolean',
        'calculate_sales_tax' => 'boolean',
        'charge_tax_on_shipping' => 'boolean',
        'tax_exempt_customers' => 'boolean',
        'calculate_purchase_tax' => 'boolean',
        'track_input_tax' => 'boolean',
        'auto_calculate_tax' => 'boolean',
        'validate_tax_rates' => 'boolean',
        'track_tax_by_jurisdiction' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'tax_inclusive_pricing' => false,
        'round_tax_per_line' => true,
        'allow_compound_tax' => true,
        'rounding_precision' => 2,
        'tax_country_code' => 'US',
        'default_reporting_frequency' => 'quarterly',
        'auto_file_tax_returns' => false,
        'tax_year_end_month' => '12',
        'tax_year_end_day' => '31',
        'calculate_sales_tax' => true,
        'charge_tax_on_shipping' => false,
        'tax_exempt_customers' => true,
        'calculate_purchase_tax' => true,
        'track_input_tax' => true,
        'auto_calculate_tax' => true,
        'validate_tax_rates' => true,
        'track_tax_by_jurisdiction' => true,
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function defaultSalesTaxRate()
    {
        return $this->belongsTo(TaxRate::class, 'default_sales_tax_rate_id', 'id');
    }

    public function defaultPurchaseTaxRate()
    {
        return $this->belongsTo(TaxRate::class, 'default_purchase_tax_rate_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Business logic methods
    public function getTaxYearEndAttribute()
    {
        return now()->month($this->tax_year_end_month)->day($this->tax_year_end_day);
    }

    public function getTaxYearStartAttribute()
    {
        return $this->getTaxYearEndAttribute()->copy()->subYear()->addDay();
    }

    public function getCurrentTaxPeriodAttribute()
    {
        $now = now();

        switch ($this->default_reporting_frequency) {
            case 'monthly':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];

            case 'quarterly':
                $quarter = ceil($now->month / 3);
                $startMonth = (($quarter - 1) * 3) + 1;

                return [
                    'start' => $now->copy()->month($startMonth)->startOfMonth(),
                    'end' => $now->copy()->month($startMonth + 2)->endOfMonth(),
                ];

            case 'annually':
                return [
                    'start' => $this->getTaxYearStartAttribute(),
                    'end' => $this->getTaxYearEndAttribute(),
                ];

            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];
        }
    }

    public function getReportingFrequencyLabelAttribute()
    {
        $labels = [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
        ];

        return $labels[$this->default_reporting_frequency] ?? $this->default_reporting_frequency;
    }

    public function getCountryLabelAttribute()
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'MX' => 'Mexico',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
        ];

        return $countries[$this->tax_country_code] ?? $this->tax_country_code;
    }

    // Tax calculation helpers
    public function shouldCalculateTaxOnAmount($amount, $isShipping = false)
    {
        if (! $this->auto_calculate_tax) {
            return false;
        }

        if ($isShipping && ! $this->charge_tax_on_shipping) {
            return false;
        }

        return $amount > 0;
    }

    public function roundTaxAmount($amount)
    {
        $precision = $this->rounding_precision;

        return round($amount, $precision);
    }

    // Static methods
    public static function getForCompany($companyId)
    {
        return static::where('company_id', $companyId)->first();
    }

    public static function getOrCreateForCompany($companyId)
    {
        $settings = static::where('company_id', $companyId)->first();

        if (! $settings) {
            $settings = static::create([
                'id' => (string) \Str::uuid(),
                'company_id' => $companyId,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }

        return $settings;
    }

    // Save hook to update the updated_by field
    protected static function booted()
    {
        static::saving(function ($settings) {
            if (auth()->check()) {
                $settings->updated_by = auth()->id();
            }
        });
    }
}
