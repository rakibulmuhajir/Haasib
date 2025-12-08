<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxExemption extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'tax.tax_exemptions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'exemption_type',
        'override_rate',
        'requires_certificate',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'override_rate' => 'decimal:4',
        'requires_certificate' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public static function createSaudiExemptions(Company $company): void
    {
        $defaults = [
            ['code' => 'VAT-EXEMPT', 'name' => 'VAT Exempt Supplies', 'exemption_type' => 'full'],
            ['code' => 'VAT-ZERO', 'name' => 'Zero Rated Supplies', 'exemption_type' => 'full'],
        ];

        foreach ($defaults as $ex) {
            static::firstOrCreate(
                ['company_id' => $company->id, 'code' => $ex['code']],
                [
                    'name' => $ex['name'],
                    'exemption_type' => $ex['exemption_type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
