<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationAdjustment extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'ledger.bank_reconciliation_adjustments';

    protected $primaryKey = 'id';

    protected $fillable = [
        'reconciliation_id',
        'company_id',
        'statement_line_id',
        'adjustment_type',
        'journal_entry_id',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'statement_line_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForReconciliation($query, $reconciliationId)
    {
        return $query->where('reconciliation_id', $reconciliationId);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithType($query, array $types)
    {
        return $query->whereIn('adjustment_type', $types);
    }

    public function scopeBankFees($query)
    {
        return $query->where('adjustment_type', 'bank_fee');
    }

    public function scopeInterest($query)
    {
        return $query->where('adjustment_type', 'interest');
    }

    public function scopeWriteOffs($query)
    {
        return $query->where('adjustment_type', 'write_off');
    }

    public function scopeTimingAdjustments($query)
    {
        return $query->where('adjustment_type', 'timing');
    }

    public function isBankFee(): bool
    {
        return $this->adjustment_type === 'bank_fee';
    }

    public function isInterest(): bool
    {
        return $this->adjustment_type === 'interest';
    }

    public function isWriteOff(): bool
    {
        return $this->adjustment_type === 'write_off';
    }

    public function isTimingAdjustment(): bool
    {
        return $this->adjustment_type === 'timing';
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format(abs($this->amount), 2);
    }

    public function getSignedAmountAttribute(): string
    {
        $prefix = $this->amount < 0 ? '-' : '+';

        return $prefix.number_format(abs($this->amount), 2);
    }

    public function getAmountTypeAttribute(): string
    {
        return $this->amount < 0 ? 'debit' : 'credit';
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match ($this->adjustment_type) {
            'bank_fee' => 'Bank Fee',
            'interest' => 'Interest',
            'write_off' => 'Write Off',
            'timing' => 'Timing Adjustment',
            default => ucfirst($this->adjustment_type),
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->adjustment_type) {
            'bank_fee' => 'currency-dollar',
            'interest' => 'chart-line',
            'write_off' => 'trash',
            'timing' => 'clock',
            default => 'circle',
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->adjustment_type) {
            'bank_fee' => 'red',
            'interest' => 'green',
            'write_off' => 'orange',
            'timing' => 'blue',
            default => 'gray',
        };
    }

    public function getCreatedAtAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Validate adjustment amount sign based on type
     */
    public function validateAmountSign(): bool
    {
        return match ($this->adjustment_type) {
            'bank_fee' => $this->amount < 0, // Bank fees should be negative
            'interest' => $this->amount >= 0, // Interest should be positive
            'write_off' => $this->amount < 0, // Write offs should be negative
            'timing' => true, // Timing can be either direction
            default => true,
        };
    }

    /**
     * Get default journal entry accounts based on adjustment type and company configuration
     */
    public function getDefaultJournalAccounts(): array
    {
        // This would typically come from company configuration
        // For now, returning common defaults
        return match ($this->adjustment_type) {
            'bank_fee' => [
                'debit_account_id' => $this->getDefaultBankFeeExpenseAccount(),
                'credit_account_id' => $this->reconciliation->ledger_account_id,
            ],
            'interest' => [
                'debit_account_id' => $this->reconciliation->ledger_account_id,
                'credit_account_id' => $this->getDefaultInterestIncomeAccount(),
            ],
            'write_off' => [
                'debit_account_id' => $this->getDefaultBadDebtExpenseAccount(),
                'credit_account_id' => $this->reconciliation->ledger_account_id,
            ],
            'timing' => [
                'debit_account_id' => $this->getDefaultTimingAdjustmentAccount(),
                'credit_account_id' => $this->reconciliation->ledger_account_id,
            ],
            default => [],
        };
    }

    private function getDefaultBankFeeExpenseAccount(): ?string
    {
        // This would come from company configuration
        return null;
    }

    private function getDefaultInterestIncomeAccount(): ?string
    {
        // This would come from company configuration
        return null;
    }

    private function getDefaultBadDebtExpenseAccount(): ?string
    {
        // This would come from company configuration
        return null;
    }

    private function getDefaultTimingAdjustmentAccount(): ?string
    {
        // This would come from company configuration
        return null;
    }

    /**
     * Create adjustment with journal entry
     */
    public static function createWithJournalEntry(array $data, User $user): self
    {
        $adjustment = new static([
            'reconciliation_id' => $data['reconciliation_id'],
            'company_id' => $data['company_id'],
            'statement_line_id' => $data['statement_line_id'] ?? null,
            'adjustment_type' => $data['adjustment_type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'created_by' => $user->id,
        ]);

        $adjustment->save();

        // Create journal entry if requested
        if ($data['post_journal_entry'] ?? true) {
            $journalData = $adjustment->getDefaultJournalAccounts();

            if (! empty($journalData)) {
                $journalEntry = JournalEntry::create([
                    'company_id' => $adjustment->company_id,
                    'journal_date' => now(),
                    'description' => $adjustment->description,
                    'reference' => 'Bank Reconciliation Adjustment #'.$adjustment->id,
                    'created_by' => $user->id,
                ]);

                // Create debit and credit transactions
                JournalTransaction::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $journalData['debit_account_id'],
                    'debit_amount' => abs($adjustment->amount),
                    'credit_amount' => 0,
                    'description' => $adjustment->description,
                ]);

                JournalTransaction::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $journalData['credit_account_id'],
                    'debit_amount' => 0,
                    'credit_amount' => abs($adjustment->amount),
                    'description' => $adjustment->description,
                ]);

                $adjustment->journal_entry_id = $journalEntry->id;
                $adjustment->save();
            }
        }

        // Log the adjustment creation
        activity()
            ->performedOn($adjustment)
            ->causedBy($user)
            ->withProperties([
                'reconciliation_id' => $adjustment->reconciliation_id,
                'adjustment_type' => $adjustment->adjustment_type,
                'amount' => $adjustment->amount,
                'journal_entry_id' => $adjustment->journal_entry_id,
            ])
            ->log('bank_reconciliation_adjustment_created');

        return $adjustment;
    }
}
