<?php

namespace App\Modules\Banking\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BankRule extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.bank_rules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'name',
        'priority',
        'conditions',
        'actions',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_account_id' => 'string',
        'priority' => 'integer',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
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

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    public function scopeForAccount($query, $accountId)
    {
        return $query->where(function ($q) use ($accountId) {
            $q->where('bank_account_id', $accountId)
              ->orWhereNull('bank_account_id');
        });
    }

    // Helper methods for rule processing
    public function matchesTransaction(BankTransaction $transaction): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $conditions = $this->conditions;

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($transaction, $condition)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateCondition(BankTransaction $transaction, array $condition): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $transactionValue = $this->getTransactionFieldValue($transaction, $field);

        return match ($operator) {
            'contains' => str_contains(strtolower($transactionValue), strtolower($value)),
            'equals' => $transactionValue === $value,
            'starts_with' => str_starts_with(strtolower($transactionValue), strtolower($value)),
            'ends_with' => str_ends_with(strtolower($transactionValue), strtolower($value)),
            'regex' => preg_match($value, $transactionValue) === 1,
            'gt' => is_numeric($transactionValue) && $transactionValue > $value,
            'lt' => is_numeric($transactionValue) && $transactionValue < $value,
            'between' => is_numeric($transactionValue) &&
                        is_array($value) &&
                        count($value) === 2 &&
                        $transactionValue >= $value[0] &&
                        $transactionValue <= $value[1],
            default => false,
        };
    }

    private function getTransactionFieldValue(BankTransaction $transaction, string $field): mixed
    {
        return match ($field) {
            'description' => $transaction->description,
            'payee_name' => $transaction->payee_name,
            'amount' => abs($transaction->amount), // Use absolute amount for rules
            'reference_number' => $transaction->reference_number,
            'category' => $transaction->category,
            'transaction_type' => $transaction->transaction_type,
            default => null,
        };
    }

    public function applyActions(BankTransaction &$transaction): array
    {
        $actions = $this->actions;
        $applied = [];

        foreach ($actions as $action) {
            $applied[] = $this->applyAction($transaction, $action);
        }

        return $applied;
    }

    private function applyAction(BankTransaction &$transaction, array $action): array
    {
        $type = $action['type'];
        $value = $action['value'] ?? null;

        return match ($type) {
            'set_category' => [
                'type' => $type,
                'old_value' => $transaction->category,
                'new_value' => $transaction->category = $value,
            ],
            'set_payee' => [
                'type' => $type,
                'old_value' => $transaction->payee_name,
                'new_value' => $transaction->payee_name = $value,
            ],
            'set_transaction_type' => [
                'type' => $type,
                'old_value' => $transaction->transaction_type,
                'new_value' => $transaction->transaction_type = $value,
            ],
            'auto_match_customer', 'auto_match_vendor' => [
                'type' => $type,
                'value' => $value,
                'note' => 'Auto-matching would be implemented in service layer',
            ],
            'set_gl_account_id' => [
                'type' => $type,
                'value' => $value,
                'note' => 'GL account assignment would be implemented in service layer',
            ],
            default => ['type' => $type, 'note' => 'Unknown action type'],
        };
    }
}