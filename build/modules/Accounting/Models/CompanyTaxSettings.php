<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CompanyTaxSettings extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.company_tax_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'tax_enabled',
        'default_jurisdiction_id',
        'default_sales_tax_rate_id',
        'default_purchase_tax_rate_id',
        'price_includes_tax',
        'rounding_mode',
        'rounding_precision',
        'tax_number_label',
        'show_tax_column',
    ];

    protected $casts = [
        'company_id' => 'string',
        'tax_enabled' => 'boolean',
        'default_jurisdiction_id' => 'string',
        'default_sales_tax_rate_id' => 'string',
        'default_purchase_tax_rate_id' => 'string',
        'price_includes_tax' => 'boolean',
        'rounding_precision' => 'integer',
        'show_tax_column' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultJurisdiction()
    {
        return $this->belongsTo(Jurisdiction::class, 'default_jurisdiction_id');
    }

    public function defaultSalesTaxRate()
    {
        return $this->belongsTo(TaxRate::class, 'default_sales_tax_rate_id');
    }

    public function defaultPurchaseTaxRate()
    {
        return $this->belongsTo(TaxRate::class, 'default_purchase_tax_rate_id');
    }

    public static function forCompany(Company $company): self
    {
        return static::firstOrCreate(['company_id' => $company->id]);
    }

    public function roundAmount(float $amount): float
    {
        $precision = $this->rounding_precision;

        return match ($this->rounding_mode) {
            'half_up' => round($amount, $precision, PHP_ROUND_HALF_UP),
            'half_down' => round($amount, $precision, PHP_ROUND_HALF_DOWN),
            'floor' => round($amount, $precision, PHP_ROUND_HALF_DOWN),
            'ceiling' => round($amount, $precision, PHP_ROUND_HALF_UP) + 0.000000001,
            'bankers' => round($amount, $precision, PHP_ROUND_HALF_EVEN),
            default => round($amount, $precision),
        };
    }

    public function calculateTaxAmount(float $baseAmount, float $rate): float
    {
        if (!$this->tax_enabled || $rate == 0) {
            return 0.0;
        }

        $taxAmount = $baseAmount * ($rate / 100);
        return $this->roundAmount($taxAmount);
    }

    public function extractTaxFromInclusivePrice(float $inclusivePrice, float $rate): float
    {
        if (!$this->tax_enabled || $rate == 0) {
            return 0.0;
        }

        $baseAmount = $inclusivePrice / (1 + ($rate / 100));
        $taxAmount = $inclusivePrice - $baseAmount;
        return $this->roundAmount($taxAmount);
    }

    public function getBaseFromInclusivePrice(float $inclusivePrice, float $rate): float
    {
        if (!$this->tax_enabled || $rate == 0) {
            return $inclusivePrice;
        }

        $baseAmount = $inclusivePrice / (1 + ($rate / 100));
        return $this->roundAmount($baseAmount);
    }
}
