<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payslip extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.payslips';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'employee_id',
        'payslip_number',
        'currency',
        'gross_pay',
        'total_earnings',
        'total_deductions',
        'employer_costs',
        'net_pay',
        'status',
        'approved_at',
        'approved_by_user_id',
        'paid_at',
        'payment_method',
        'payment_reference',
        'gl_transaction_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'payroll_period_id' => 'string',
        'employee_id' => 'string',
        'gross_pay' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'employer_costs' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'approved_at' => 'datetime',
        'approved_by_user_id' => 'string',
        'paid_at' => 'datetime',
        'gl_transaction_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->where('line_type', 'earning');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->where('line_type', 'deduction');
    }

    public function employerCosts(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->where('line_type', 'employer');
    }
}
