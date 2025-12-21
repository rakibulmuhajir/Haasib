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
        'industry_code',
        'slug',
        'country',
        'country_id',
        'base_currency',
        'language',
        'locale',
        'registration_number',
        'trade_name',
        'timezone',
        'fiscal_year_start_month',
        'period_frequency',
        'invoice_prefix',
        'invoice_start_number',
        'bill_prefix',
        'bill_start_number',
        'default_customer_payment_terms',
        'default_vendor_payment_terms',
        'tax_registered',
        'tax_rate',
        'tax_inclusive',
        'onboarding_completed',
        'onboarding_completed_at',
        'ar_account_id',
        'ap_account_id',
        'income_account_id',
        'expense_account_id',
        'bank_account_id',
        'retained_earnings_account_id',
        'sales_tax_payable_account_id',
        'purchase_tax_receivable_account_id',
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

    protected function industryCode(): Attribute
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

    public function isModuleEnabled(string $moduleKey): bool
    {
        $modules = (array) ($this->settings['modules'] ?? []);

        $defaults = [
            // Inventory is available by default, but can be explicitly disabled per company.
            'inventory' => true,
        ];

        return (bool) ($modules[$moduleKey] ?? ($defaults[$moduleKey] ?? false));
    }

    public function enableModule(string $moduleKey): void
    {
        $settings = (array) ($this->settings ?? []);
        $modules = (array) ($settings['modules'] ?? []);
        $modules[$moduleKey] = true;

        $this->settings = array_merge($settings, ['modules' => $modules]);
        $this->save();
    }

    public function disableModule(string $moduleKey): void
    {
        $settings = (array) ($this->settings ?? []);
        $modules = (array) ($settings['modules'] ?? []);
        $modules[$moduleKey] = false;

        $this->settings = array_merge($settings, ['modules' => $modules]);
        $this->save();
    }
}
