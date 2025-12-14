<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.journal_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'transaction_id',
        'account_id',
        'line_number',
        'description',
        'debit_amount',
        'credit_amount',
        'currency_debit',
        'currency_credit',
        'exchange_rate',
        'reference_type',
        'reference_id',
        'dimension_1',
        'dimension_2',
        'dimension_3',
    ];

    protected $casts = [
        'company_id' => 'string',
        'transaction_id' => 'string',
        'account_id' => 'string',
        'line_number' => 'integer',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'currency_debit' => 'decimal:6',
        'currency_credit' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'reference_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
