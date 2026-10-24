<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\JournalReversalService;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\AmanatTransactionChange;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fixing an amanat movement after it was entered.
 *
 *  - changeHolder: any source. Moves the row to another holder and both cached balances with it;
 *    the books do not change (one Amanat liability account), only a line's holder name does.
 *    A Daily Close row also moves inside that close's saved form so a reopen shows the new holder.
 *  - amend: a standalone deposit or withdrawal only. Reverses its journal, voids the row (kept,
 *    pointing at its replacement) and posts the corrected movement through AmanatService.
 *    A Daily Close row is corrected in the Daily Close instead.
 *
 * Every change leaves a row in fuel.amanat_transaction_changes.
 */
class AmanatCorrectionService
{
    public const SOURCE_CLOSE = 'close';
    public const SOURCE_STANDALONE = 'standalone';
    public const SOURCE_OTHER = 'other';

    /** Standalone journal types by movement type: only deposits and withdrawals can be re-amended. */
    private const AMENDABLE = [
        AmanatTransaction::TYPE_DEPOSIT => 'amanat_deposit',
        AmanatTransaction::TYPE_WITHDRAWAL => 'amanat_withdrawal',
    ];

    public function __construct(
        private readonly AmanatService $amanat,
        private readonly JournalReversalService $reversal,
    ) {}

    /**
     * Where a movement came from: its Daily Close, a standalone entry on the holder page, or
     * something else (an opening balance, a legacy row with no journal).
     *
     * @return array{kind: string, transaction: ?Transaction}
     */
    public function sourceOf(AmanatTransaction $movement): array
    {
        $transaction = $movement->journal_entry_id ? $movement->journalEntry?->transaction : null;
        if ($transaction && $transaction->company_id !== $movement->company_id) {
            $transaction = null;
        }
        $kind = match (true) {
            $transaction === null => self::SOURCE_OTHER,
            $transaction->transaction_type === 'fuel_daily_close' => self::SOURCE_CLOSE,
            $transaction->reference_type === 'fuel.amanat_transactions'
                && in_array($transaction->transaction_type, ['amanat_deposit', 'amanat_withdrawal', 'amanat_fuel_purchase'], true) => self::SOURCE_STANDALONE,
            default => self::SOURCE_OTHER,
        };

        return ['kind' => $kind, 'transaction' => $transaction];
    }

    public function changeHolder(Company $company, User $user, string $movementId, string $newCustomerId): AmanatTransaction
    {
        return AccountingWriteTransaction::run(function () use ($company, $user, $movementId, $newCustomerId) {
            $movement = $this->lockMovement($company, $movementId);
            $source = $this->sourceOf($movement);

            if ($movement->journal_entry_id && $source['kind'] === self::SOURCE_OTHER) {
                throw ValidationException::withMessages(['customer_id' => 'Opening balances are changed in Opening balances.']);
            }
            if ($movement->customer_id === $newCustomerId) {
                throw ValidationException::withMessages(['customer_id' => 'Pick a different holder.']);
            }
            if ($source['kind'] === self::SOURCE_STANDALONE && $movement->isFuelPurchase() && $this->hasInvoice($movement)) {
                throw ValidationException::withMessages(['customer_id' => 'This came from a fuel sale. Correct the sale instead.']);
            }
            $this->assertDayOpen($company, $source);

            $newCustomer = Customer::where('company_id', $company->id)->find($newCustomerId);
            if (! $newCustomer) {
                throw ValidationException::withMessages(['customer_id' => 'Pick an amanat holder.']);
            }
            // Both profiles are locked in id order so two opposite moves cannot deadlock.
            $profiles = [];
            foreach (collect([$movement->customer_id, $newCustomer->id])->sort()->values() as $id) {
                $profiles[$id] = $this->lockProfile($company->id, $id);
            }
            $newProfile = $profiles[$newCustomer->id];
            $oldProfile = $profiles[$movement->customer_id];
            if (! $newProfile->is_amanat_holder) {
                throw ValidationException::withMessages(['customer_id' => 'Pick an amanat holder.']);
            }
            $oldCustomer = Customer::where('company_id', $company->id)->find($movement->customer_id);

            $amount = (float) $movement->amount;
            $isDeposit = $movement->isDeposit();
            // A deposit leaving a holder takes money out of their balance; a withdrawal or fuel
            // purchase moving off them gives it back, and the new holder now bears it.
            $drawn = $isDeposit ? $oldProfile : $newProfile;
            if (! $drawn->canDrawAmanat($amount)) {
                $name = ($isDeposit ? $oldCustomer?->name : $newCustomer->name) ?? 'Selected holder';
                throw ValidationException::withMessages([
                    'customer_id' => "{$name} has only {$drawn->amanat_balance} available in Amanat.",
                ]);
            }

            // Before anything is written: a close whose form cannot be matched is refused whole.
            if ($source['kind'] === self::SOURCE_CLOSE) {
                $this->retargetCloseForm($source['transaction'], $movement, $oldCustomer?->id, $newCustomer);
            }

            $oldProfile->adjustAmanatBalance($isDeposit ? -$amount : $amount);
            $newProfile->adjustAmanatBalance($isDeposit ? $amount : -$amount);
            $movement->forceFill(['customer_id' => $newCustomer->id])->save();

            if ($source['kind'] === self::SOURCE_STANDALONE && ! $source['transaction']->is_locked) {
                $this->renameInJournal($source['transaction'], $oldCustomer?->name, $newCustomer->name);
            }

            $what = ['amanat_transaction_id' => $movement->id, 'type' => $movement->transaction_type, 'amount' => $amount];
            $this->record($company, $user, $movement, 'holder_changed',
                $what + ['customer_id' => $oldCustomer?->id, 'customer_name' => $oldCustomer?->name],
                $what + ['customer_id' => $newCustomer->id, 'customer_name' => $newCustomer->name],
            );

            return $movement->fresh();
        });
    }

    /**
     * @param  array{business_date: string, amount: float|int|string, payment_account_id?: ?string, reference?: ?string, notes?: ?string}  $data
     */
    public function amend(Company $company, User $user, string $movementId, array $data): AmanatTransaction
    {
        return AccountingWriteTransaction::run(function () use ($company, $user, $movementId, $data) {
            $movement = $this->lockMovement($company, $movementId);
            $source = $this->sourceOf($movement);

            if ($source['kind'] === self::SOURCE_CLOSE) {
                throw ValidationException::withMessages(['amount' => 'Correct this in Daily Close.']);
            }
            if ($source['kind'] !== self::SOURCE_STANDALONE || ! isset(self::AMENDABLE[$movement->transaction_type])) {
                throw ValidationException::withMessages(['amount' => 'This entry cannot be edited here.']);
            }

            $customer = Customer::where('company_id', $company->id)->findOrFail($movement->customer_id);
            $profile = $this->lockProfile($company->id, $customer->id);
            $oldAmount = (float) $movement->amount;
            $newAmount = round((float) $data['amount'], 2);

            // A smaller deposit must not leave the holder below zero (a withdrawal is checked by
            // AmanatService::withdraw once the original has been given back).
            if ($movement->isDeposit() && ! $profile->allow_amanat_borrowing
                && (float) $profile->amanat_balance - $oldAmount + $newAmount < -0.004) {
                throw ValidationException::withMessages([
                    'amount' => "{$customer->name} has only {$profile->amanat_balance} available in Amanat.",
                ]);
            }

            $before = $this->snapshot($movement, $source['transaction']);

            $originalDate = \Carbon\Carbon::parse($source['transaction']->transaction_date)->toDateString();
            $this->reversal->reverse($company, JournalReversalService::AMANAT, $source['transaction']->id, 'Amanat entry corrected', $originalDate);

            $profile->adjustAmanatBalance($movement->isDeposit() ? -$oldAmount : $oldAmount);
            $movement->forceFill(['voided_at' => now(), 'voided_by_user_id' => $user->id])->save();

            $replacement = $this->amanat->{$movement->isDeposit() ? 'deposit' : 'withdraw'}($customer, [
                'business_date' => $data['business_date'],
                'amount' => $newAmount,
                'payment_account_id' => array_key_exists('payment_account_id', $data) ? $data['payment_account_id'] : $movement->payment_account_id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $movement->forceFill(['replaced_by_id' => $replacement->id])->save();

            $this->record($company, $user, $movement, 'amount_corrected', $before, $this->snapshot($replacement, $replacement->journalEntry?->transaction));

            return $replacement;
        });
    }

    /** A holder change touches no totals, but it must not rewrite a closed period or a locked day. */
    private function assertDayOpen(Company $company, array $source): void
    {
        $journal = $source['transaction'];
        if (! $journal) {
            return;
        }
        if ($source['kind'] === self::SOURCE_CLOSE && $journal->is_locked) {
            throw ValidationException::withMessages(['customer_id' => 'Unlock this day before changing its holder.']);
        }
        $date = \Carbon\Carbon::parse($journal->transaction_date)->toDateString();
        app(DocumentDateLock::class)->assertOpen($company->id, $date, 'This entry');
    }

    /** An amanat fuel purchase that settles a fuel sale carries that sale's invoice number as its reference. */
    private function hasInvoice(AmanatTransaction $movement): bool
    {
        return $movement->reference !== null && Invoice::where('company_id', $movement->company_id)
            ->where('invoice_number', $movement->reference)->exists();
    }

    private function lockMovement(Company $company, string $movementId): AmanatTransaction
    {
        $movement = AmanatTransaction::where('company_id', $company->id)->lockForUpdate()->find($movementId);
        if (! $movement) {
            throw ValidationException::withMessages(['id' => 'That amanat entry was not found.']);
        }
        if ($movement->isVoided()) {
            throw ValidationException::withMessages(['id' => 'This entry was already corrected.']);
        }

        return $movement;
    }

    private function lockProfile(string $companyId, string $customerId): CustomerProfile
    {
        CustomerProfile::getOrCreateForCustomer($companyId, $customerId);

        return CustomerProfile::where('company_id', $companyId)->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
    }

    private function snapshot(AmanatTransaction $movement, ?Transaction $journal = null): array
    {
        return [
            'amanat_transaction_id' => $movement->id,
            'amount' => (float) $movement->amount,
            'payment_account_id' => $movement->payment_account_id,
            'reference' => $movement->reference,
            'notes' => $movement->notes,
            'date' => $journal?->transaction_date ? \Carbon\Carbon::parse($journal->transaction_date)->toDateString() : null,
        ];
    }

    private function record(Company $company, User $user, AmanatTransaction $movement, string $action, array $before, array $after): void
    {
        AmanatTransactionChange::create([
            'company_id' => $company->id,
            'amanat_transaction_id' => $movement->id,
            'user_id' => $user->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }

    /** A standalone journal's descriptions carry the holder's name; keep them readable. Totals are untouched. */
    private function renameInJournal(Transaction $journal, ?string $from, string $to): void
    {
        if (! $from || $from === $to) {
            return;
        }
        $rename = fn (?string $text) => $text === null ? null : str_replace($from, $to, $text);

        DB::table('acct.transactions')->where('id', $journal->id)->where('company_id', $journal->company_id)
            ->update(['description' => $rename($journal->description)]);
        foreach (JournalEntry::where('company_id', $journal->company_id)->where('transaction_id', $journal->id)->get(['id', 'description']) as $line) {
            DB::table('acct.journal_entries')->where('id', $line->id)->update(['description' => $rename($line->description)]);
        }
    }

    /**
     * A posted close keeps its form (to prefill a reopen) and its detail lists (the month summary,
     * the day sheet). Its journal lines are immutable and carry no holder, so only these move.
     */
    private function retargetCloseForm(Transaction $close, AmanatTransaction $movement, ?string $oldId, Customer $new): void
    {
        // The locked, current copy of the close is the one rewritten, never a stale one.
        $close = Transaction::where('company_id', $close->company_id)->lockForUpdate()->findOrFail($close->id);
        $metadata = $close->metadata ?? [];
        $deposit = $movement->isDeposit();
        $amount = (float) $movement->amount;
        $kind = $movement->isFuelPurchase() ? 'fuel_purchase' : 'withdrawal';

        $lists = $deposit
            ? [['form_input', 'amanat_deposits'], ['amanat_deposit_details']]
            : [['form_input', 'amanat_disbursements'], ['amanat_disbursement_details']];

        $formMatched = false;
        foreach ($lists as $path) {
            $rows = $metadata;
            foreach ($path as $key) {
                $rows = $rows[$key] ?? null;
                if ($rows === null) {
                    continue 2;
                }
            }
            foreach ($rows as $i => $row) {
                $rowAccount = ($row['payment_account_id'] ?? null) ?: null;
                if (($row['customer_id'] ?? null) === $oldId
                    && abs((float) ($row['amount'] ?? 0) - $amount) < 0.005
                    && ($deposit || ($row['kind'] ?? 'withdrawal') === $kind)
                    && ($rowAccount === null || $movement->payment_account_id === null || $rowAccount === $movement->payment_account_id)) {
                    $rows[$i]['customer_id'] = $new->id;
                    if (array_key_exists('customer_name', $row)) {
                        $rows[$i]['customer_name'] = $new->name;
                    }
                    if ($path[0] === 'form_input') {
                        $formMatched = true;
                    }
                    break;
                }
            }
            $target = &$metadata;
            foreach ($path as $key) {
                $target = &$target[$key];
            }
            $target = $rows;
            unset($target);
        }

        if (! $formMatched) {
            throw ValidationException::withMessages([
                'customer_id' => "Couldn't find this deposit in the day's form. Correct it in Daily Close.",
            ]);
        }

        // A posted close's metadata is guarded by fuel.protect_close_snapshot; the reopening
        // setting is the sanctioned bypass (see DailyCloseCostCorrectionService::fold).
        try {
            DB::select("SELECT set_config('app.reopening_close_id', ?, true)", [$close->id]);
            DB::table('acct.transactions')->where('id', $close->id)->where('company_id', $close->company_id)
                ->update(['metadata' => json_encode($metadata)]);
        } finally {
            DB::select("SELECT set_config('app.reopening_close_id', '', true)");
        }
    }
}
