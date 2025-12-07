<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringBillSchedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.recurring_bill_schedules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'name',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_bill_date',
        'last_generated_at',
        'template_data',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'interval' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_bill_date' => 'date',
        'last_generated_at' => 'datetime',
        'template_data' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'recurring_schedule_id');
    }
}
