<?php

namespace Modules\Payroll\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BenefitPlan extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.benefit_plans';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'benefit_type',
        'provider',
        'employee_contrib_rate',
        'employer_contrib_rate',
        'employee_fixed_amount',
        'employer_fixed_amount',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'employee_contrib_rate' => 'decimal:4',
        'employer_contrib_rate' => 'decimal:4',
        'employee_fixed_amount' => 'decimal:2',
        'employer_fixed_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeBenefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }
}
