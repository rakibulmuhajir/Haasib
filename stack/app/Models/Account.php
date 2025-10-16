<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    protected $fillable = [
        'company_id',
        'account_group_id',
        'code',
        'name',
        'description',
        'normal_balance',
        'account_type',
        'active',
        'allow_manual_entries',
        'currency',
        'opening_balance',
        'opening_balance_date',
        'current_balance',
        'parent_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allow_manual_entries' => 'boolean',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'current_balance' => 'decimal:2',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the company that owns the account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the account group that contains the account.
     */
    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    /**
     * Get the parent account.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }
}
