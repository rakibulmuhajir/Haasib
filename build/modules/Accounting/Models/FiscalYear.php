<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.fiscal_years';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
        'is_closed',
        'status',
        'closed_at',
        'closed_by_user_id',
        'retained_earnings_account_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
        'closed_by_user_id' => 'string',
        'retained_earnings_account_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function periods()
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'fiscal_year_id');
    }
}
