<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxAgency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.tax_agencies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'name',
        'tax_id',
        'country_code',
        'state_province',
        'city',
        'phone',
        'email',
        'website',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'reporting_frequency',
        'filing_method',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'created_by' => 'string',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class, 'tax_agency_id', 'id');
    }

    public function taxReturns()
    {
        return $this->hasMany(TaxReturn::class, 'tax_agency_id', 'id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeByReportingFrequency($query, $frequency)
    {
        return $query->where('reporting_frequency', $frequency);
    }

    // Business logic methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function activate()
    {
        $this->is_active = true;

        return $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;

        return $this->save();
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state_province,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }

    public function getReportingFrequencyLabelAttribute()
    {
        $labels = [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
        ];

        return $labels[$this->reporting_frequency] ?? $this->reporting_frequency;
    }

    public function getFilingMethodLabelAttribute()
    {
        $labels = [
            'electronic' => 'Electronic',
            'paper' => 'Paper',
            'auto' => 'Automatic',
        ];

        return $labels[$this->filing_method] ?? $this->filing_method;
    }

    // Business rules
    public function canBeDeleted()
    {
        // Check if agency has associated tax rates or tax returns
        return ! $this->taxRates()->exists() && ! $this->taxReturns()->exists();
    }

    public function getNextFilingDate()
    {
        $now = now();

        switch ($this->reporting_frequency) {
            case 'monthly':
                return $now->endOfMonth()->addDay();
            case 'quarterly':
                $quarter = ceil($now->month / 3);
                if ($quarter == 4) {
                    return $now->copy()->month(1)->year($now->year + 1)->endOfQuarter()->addDay();
                }

                return $now->copy()->month(($quarter * 3) + 1)->endOfQuarter()->addDay();
            case 'annually':
                return $now->copy()->year($now->year + 1)->endOfYear()->addDay();
            default:
                return null;
        }
    }
}
