<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBenefit extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.employee_benefits';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'benefit_plan_id',
        'start_date',
        'end_date',
        'employee_override_amount',
        'employer_override_amount',
        'coverage_level',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'employee_id' => 'string',
        'benefit_plan_id' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'employee_override_amount' => 'decimal:2',
        'employer_override_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function benefitPlan(): BelongsTo
    {
        return $this->belongsTo(BenefitPlan::class);
    }
}
