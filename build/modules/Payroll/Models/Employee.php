<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'pay.employees';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'national_id',
        'tax_id',
        'address',
        'hire_date',
        'termination_date',
        'termination_reason',
        'employment_type',
        'employment_status',
        'department',
        'position',
        'manager_id',
        'pay_frequency',
        'base_salary',
        'hourly_rate',
        'currency',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'bank_routing_number',
        'notes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'user_id' => 'string',
        'date_of_birth' => 'date',
        'address' => 'array',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'manager_id' => 'string',
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:4',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'national_id',
        'bank_account_number',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function outstandingAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class)
            ->whereIn('status', ['pending', 'partially_recovered']);
    }

    /**
     * Get total outstanding advance amount for this employee
     */
    public function getTotalOutstandingAdvancesAttribute(): float
    {
        return (float) $this->outstandingAdvances()->sum('amount_outstanding');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
