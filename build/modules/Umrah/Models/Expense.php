<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $connection = 'pgsql';

    protected $table = 'umrah.expenses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'expense_number',
        'expense_date',
        'expense_account_id',
        'payment_account_id',
        'payee',
        'description',
        'reference',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'transaction_id',
        'status',
        'reversed_at',
        'reversed_by_user_id',
        'reversal_reason',
        'reversal_transaction_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'expense_date' => 'date',
        'expense_account_id' => 'string',
        'payment_account_id' => 'string',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'transaction_id' => 'string',
        'reversed_at' => 'datetime',
        'reversed_by_user_id' => 'string',
        'reversal_transaction_id' => 'string',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }
}
