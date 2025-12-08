<?php

namespace App\Modules\Banking\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BankReconciliation extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'bank.bank_reconciliations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_date',
        'statement_ending_balance',
        'book_balance',
        'reconciled_balance',
        'difference',
        'status',
        'started_at',
        'completed_at',
        'completed_by_user_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_account_id' => 'string',
        'statement_date' => 'date',
        'statement_ending_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'reconciled_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completed_by_user_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function completedByUser()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // Scopes for status
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function isBalanced()
    {
        return $this->difference == 0;
    }

    public function canComplete()
    {
        return $this->isBalanced() && $this->isInProgress();
    }
}