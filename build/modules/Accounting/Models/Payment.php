<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.payments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'payment_method',
        'reference_number',
        'notes',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }
}
