<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Check if rule applies to all bank accounts.
     */
    public function appliesToAllAccounts(): bool
    {
        return is_null($this->bank_account_id);
    }

    /**
     * Scope for active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules ordered by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }

    /**
     * Check if a transaction matches this rule's conditions.
     */
    public function matches(BankTransaction $transaction): bool
    {
        foreach ($this->conditions as $condition) {
            if (! $this->matchCondition($transaction, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match a single condition against a transaction.
     */
    protected function matchCondition(BankTransaction $transaction, array $condition): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'contains';
        $value = $condition['value'] ?? '';

        if (! $field) {
            return false;
        }

        $fieldValue = $transaction->{$field} ?? '';

        return match ($operator) {
            'contains' => str_contains(strtolower((string) $fieldValue), strtolower($value)),
            'equals' => strtolower((string) $fieldValue) === strtolower($value),
            'starts_with' => str_starts_with(strtolower((string) $fieldValue), strtolower($value)),
            'ends_with' => str_ends_with(strtolower((string) $fieldValue), strtolower($value)),
            'regex' => (bool) preg_match($value, (string) $fieldValue),
            'gt' => (float) $fieldValue > (float) $value,
            'lt' => (float) $fieldValue < (float) $value,
            'between' => $this->matchBetween($fieldValue, $value),
            default => false,
        };
    }

    /**
     * Match a between condition.
     */
    protected function matchBetween($fieldValue, $value): bool
    {
        if (! is_array($value) || count($value) < 2) {
            return false;
        }

        $numValue = (float) $fieldValue;

        return $numValue >= (float) $value[0] && $numValue <= (float) $value[1];
    }

    /**
     * Apply this rule's actions to a transaction.
     */
    public function applyTo(BankTransaction $transaction): void
    {
        foreach ($this->actions as $action => $actionValue) {
            match ($action) {
                'set_category' => $transaction->category = $actionValue,
                'set_payee' => $transaction->payee_name = $actionValue,
                'set_gl_account_id' => $transaction->gl_account_id = $actionValue,
                'set_transaction_type' => $transaction->transaction_type = $actionValue,
                default => null,
            };
        }
    }
}
