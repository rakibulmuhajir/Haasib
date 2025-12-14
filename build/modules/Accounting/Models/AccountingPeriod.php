<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.accounting_periods';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'name',
        'period_number',
        'start_date',
        'end_date',
        'period_type',
        'is_closed',
        'is_adjustment',
        'closed_at',
        'closed_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'fiscal_year_id' => 'string',
        'period_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'is_adjustment' => 'boolean',
        'closed_at' => 'datetime',
        'closed_by_user_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'period_id');
    }
}
