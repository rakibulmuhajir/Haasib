<?php

namespace App\Modules\Accounting\Models;

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

    public const TYPE_WALK_IN = 'walk_in';
    public const TYPE_AGENT = 'agent';
    public const TYPE_B2B = 'b2b';

    /**
     * What kind of buyer this is, in the same spirit as Vendor::TYPES.
     *
     * Nothing branches on the value. An agent is invoiced, aged and posted
     * exactly like a walk-in; the type is what lets a register be filtered and
     * what gives a module-specific profile something to hang off. umrah.agents
     * extends the agent-typed customer rather than duplicating it.
     */
    public const TYPES = [
        self::TYPE_WALK_IN => 'Walk-in customer',
        self::TYPE_AGENT => 'Agent',
        self::TYPE_B2B => 'Business account',
    ];

    protected $fillable = [
        'company_id',
        'customer_number',
        'name',
        'customer_type',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'tax_id',
        'base_currency',
        'payment_terms',
        'credit_limit',
        'ar_account_id',
        'notes',
        'logo_url',
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
        'ar_account_id' => 'string',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
