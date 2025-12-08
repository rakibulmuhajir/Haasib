<?php

namespace App\Modules\Banking\Models;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CompanyBankAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'bank.company_bank_accounts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_id',
        'gl_account_id',
        'account_name',
        'account_number',
        'account_type',
        'currency',
        'iban',
        'swift_code',
        'routing_number',
        'branch_name',
        'branch_address',
        'opening_balance',
        'opening_balance_date',
        'current_balance',
        'last_reconciled_balance',
        'last_reconciled_date',
        'is_primary',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_id' => 'string',
        'gl_account_id' => 'string',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'current_balance' => 'decimal:2',
        'last_reconciled_balance' => 'decimal:2',
        'last_reconciled_date' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function reconciliations()
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function bankRules()
    {
        return $this->hasMany(BankRule::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}