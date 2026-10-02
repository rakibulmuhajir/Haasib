<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.time_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'work_date',
        'hours',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** True when that employee's payslip for the month holding $date is already approved or paid. */
    public static function monthIsLocked(string $companyId, string $employeeId, \DateTimeInterface|string $date): bool
    {
        $start = \Illuminate\Support\Carbon::parse($date)->startOfMonth()->toDateString();

        return Payslip::where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'paid'])
            ->whereIn('payroll_period_id', PayrollPeriod::where('company_id', $companyId)
                ->whereDate('period_start', $start)->select('id'))
            ->exists();
    }
}
