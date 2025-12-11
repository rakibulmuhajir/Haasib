<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Company extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.companies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id', 'created_at', 'updated_at', 'is_active'];

    protected $casts = [
        'settings' => 'array',
        'created_by_user_id' => 'string',
        'is_active' => 'boolean',
        'tax_registered' => 'boolean',
        'tax_inclusive' => 'boolean',
        'onboarding_completed' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'ar_account_id' => 'string',
        'ap_account_id' => 'string',
        'income_account_id' => 'string',
        'expense_account_id' => 'string',
        'bank_account_id' => 'string',
        'retained_earnings_account_id' => 'string',
        'sales_tax_payable_account_id' => 'string',
        'purchase_tax_receivable_account_id' => 'string',
    ];

    protected $fillable = [
        'name',
        'industry',
        'slug',
        'country',
        'country_id',
        'base_currency',
        'language',
        'locale',
        'settings',
        'logo_url',
        'created_by_user_id',
    ];

    protected function industry(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value ? strtolower($value) : null,
        );
    }

    public function fiscalYears()
    {
        return $this->hasMany(\App\Modules\Accounting\Models\FiscalYear::class, 'company_id');
    }

    public function currentFiscalYear()
    {
        return $this->hasOne(\App\Modules\Accounting\Models\FiscalYear::class, 'company_id')
            ->where('is_current', true)
            ->where('is_closed', false);
    }

    public function accountingPeriods()
    {
        return $this->hasManyThrough(
            \App\Modules\Accounting\Models\AccountingPeriod::class,
            \App\Modules\Accounting\Models\FiscalYear::class,
            'company_id',
            'fiscal_year_id'
        );
    }

    public function onboarding()
    {
        return $this->hasOne(\App\Models\CompanyOnboarding::class, 'company_id');
    }

    public function getFiscalYearStartMonth(): int
    {
        return $this->fiscal_year_start_month ?? 1; // Default to January
    }

    public function setFiscalYearStartMonth(int $month): void
    {
        $this->fiscal_year_start_month = $month;
        $this->save();
    }

    public function getAutoCreateFiscalYear(): bool
    {
        return $this->settings['auto_create_fiscal_year'] ?? true;
    }

    public function setAutoCreateFiscalYear(bool $autoCreate): void
    {
        $this->settings = array_merge($this->settings ?? [], [
            'auto_create_fiscal_year' => $autoCreate
        ]);
        $this->save();
    }

    public function getDefaultPeriodType(): string
    {
        return $this->settings['default_period_type'] ?? 'monthly';
    }

    public function setDefaultPeriodType(string $periodType): void
    {
        $this->settings = array_merge($this->settings ?? [], [
            'default_period_type' => $periodType
        ]);
        $this->save();
    }
}
