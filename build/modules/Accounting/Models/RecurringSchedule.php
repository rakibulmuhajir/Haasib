<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringSchedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.recurring_schedules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'frequency',
        'interval',
        'start_date',
        'end_date',
        'next_invoice_date',
        'last_generated_at',
        'template_data',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'interval' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_invoice_date' => 'date',
        'last_generated_at' => 'datetime',
        'template_data' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
