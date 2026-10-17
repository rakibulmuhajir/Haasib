<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use App\Services\AccountingWriteTransaction;
use App\Support\CompanyClock;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Takes back a journal that was typed in by hand (a standalone expense, a deposit, withdrawal,
 * transfer or bank charge) with a visible reversal entry, never by deleting or editing it.
 *
 * The reversal is posted on the company's today unless a date is given, through the same
 * PostingService::reverseTransaction every document void uses. On top of that it refuses:
 *   - a journal of another kind (an invoice's, a payment's, a bank-feed match: those have their own void),
 *   - one that is itself a reversal, or was already reversed,
 *   - a day that is locked (a closed period or a daily close) for the original or for the reversal.
 */
class JournalReversalService
{
    public const EXPENSE = 'expense';

    public const BANK_TRANSACTION = 'bank_transaction';

    private const LABELS = [
        self::EXPENSE => 'expense',
        self::BANK_TRANSACTION => 'bank transaction',
    ];

    public function __construct(
        private readonly PostingService $posting,
        private readonly DocumentDateLock $lock,
    ) {}

    /**
     * @return array{original: Transaction, reversal: Transaction}
     */
    public function reverse(Company $company, string $kind, string $transactionId, ?string $reason = null, ?string $date = null): array
    {
        $label = self::LABELS[$kind] ?? throw new \InvalidArgumentException("Unknown journal kind: {$kind}");

        return AccountingWriteTransaction::run(function () use ($company, $kind, $label, $transactionId, $reason, $date) {
            $original = Transaction::where('company_id', $company->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->find($transactionId);

            if (! $original || ! $this->isKind($original, $kind)) {
                throw ValidationException::withMessages(['id' => "That is not a {$label} entered here."]);
            }
            if ($original->reversal_of_id) {
                throw ValidationException::withMessages(['id' => 'This entry is already a reversal.']);
            }
            if ($original->reversed_by_id || $this->hasReversal($original)) {
                throw ValidationException::withMessages(['id' => "This {$label} is already reversed."]);
            }

            $reversalDate = $date ?: app(CompanyClock::class)->today();
            $this->lock->assertOpen($company->id, Carbon::parse($original->transaction_date)->toDateString(), "This {$label}");
            $this->lock->assertOpen($company->id, Carbon::parse($reversalDate)->toDateString(), 'The reversal date');

            $reversal = $this->posting->reverseTransaction($original, $reason, Carbon::parse($reversalDate));

            return ['original' => $original->fresh(), 'reversal' => $reversal];
        });
    }

    /** Whether the journal was made by the entry point of this kind (and by nothing that owns its own void). */
    private function isKind(Transaction $transaction, string $kind): bool
    {
        return match ($kind) {
            self::EXPENSE => $transaction->reference_type === 'acct.expenses' && $transaction->transaction_type === 'expense',
            // Bank-feed matches reference their statement line; a hand-typed movement references nothing.
            self::BANK_TRANSACTION => $transaction->reference_type === 'acct.bank_transactions' && $transaction->reference_id === null,
            default => false,
        };
    }

    private function hasReversal(Transaction $original): bool
    {
        return Transaction::where('company_id', $original->company_id)
            ->where('reversal_of_id', $original->id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
