<?php

namespace Modules\Payroll\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.leave_types';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_paid',
        'accrual_rate_hours',
        'max_carryover_hours',
        'max_balance_hours',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'is_paid' => 'boolean',
        'accrual_rate_hours' => 'decimal:3',
        'max_carryover_hours' => 'decimal:3',
        'max_balance_hours' => 'decimal:3',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
