<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxGroup extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'tax.tax_groups';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'jurisdiction_id',
        'code',
        'name',
        'is_default',
        'is_active',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'jurisdiction_id' => 'string',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jurisdiction()
    {
        return $this->belongsTo(Jurisdiction::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function taxGroupComponents()
    {
        return $this->hasMany(TaxGroupComponent::class);
    }

    public function taxRates()
    {
        return $this->belongsToMany(TaxRate::class, 'tax.tax_group_components')
            ->withPivot('priority')
            ->orderByPivot('priority');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public static function createSaudiVATGroup(Company $company): self
    {
        $jurisdiction = Jurisdiction::getSaudiArabia();

        return static::create([
            'company_id' => $company->id,
            'jurisdiction_id' => $jurisdiction->id,
            'code' => 'VAT-SA-GROUP',
            'name' => 'Saudi VAT Group',
            'is_default' => true,
            'is_active' => true,
            'description' => 'Saudi VAT combined group',
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function getCombinedRate(): float
    {
        return $this->taxRates->sum('rate');
    }
}
