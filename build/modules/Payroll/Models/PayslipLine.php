<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.payslip_lines';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'payslip_id',
        'line_type',
        'earning_type_id',
        'deduction_type_id',
        'description',
        'quantity',
        'rate',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'payslip_id' => 'string',
        'earning_type_id' => 'string',
        'deduction_type_id' => 'string',
        'quantity' => 'decimal:3',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function earningType(): BelongsTo
    {
        return $this->belongsTo(EarningType::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class);
    }
}
