<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.customers';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'tax_id',
        'base_currency',
        'payment_terms',
        'credit_limit',
        'notes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'credit_limit' => 'decimal:2',
        'payment_terms' => 'integer',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
