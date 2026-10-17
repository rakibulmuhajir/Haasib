<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Actions\BillPayment\CreateAction as BillPaymentCreateAction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\WithholdingTax;
use App\Modules\FuelStation\Models\CardTaxDeduction;
use App\Modules\FuelStation\Models\StationSettings;
use App\Services\AccountingWriteTransaction;
use App\Services\CommandBus;
use App\Support\CompanyClock;
use Illuminate\Validation\ValidationException;

/**
 * Tax a card company deducted from card sales -- the bank on POS swipes, or the fuel supplier on
 * its vendor card -- entered by hand on any date, usually month end when the statement shows it.
 * It is advance income tax (WithholdingTax::advanceTaxAccount), and it comes out of wherever that
 * channel's money is:
 *   - settles to clearing: Dr advance tax / Cr the channel's clearing account (less to collect);
 *   - settles to bank:     Dr advance tax / Cr the channel's bank account (less arrived);
 *   - settles to supplier: a bill to that supplier, line on advance tax -- the supplier credited
 *     us that much less against our fuel bills, so we owe it that much more.
 * Gated by the company's tax switch, like all withholding tax.
 */
class CardTaxService
{
    public const TYPES = ['card_pos', 'fuel_card'];

    /** The station's enabled card channels, with where each one's tax would come from. */
    public function channels(Company $company): array
    {
        $settings = StationSettings::where('company_id', $company->id)->first();

        return collect($settings?->payment_channels ?? [])
            ->filter(fn ($c) => ($c['enabled'] ?? false) && in_array($c['type'] ?? null, self::TYPES, true))
            ->map(function ($c) use ($company) {
                $settlesTo = $c['settles_to'] ?? 'clearing';
                $vendor = $settlesTo === 'supplier' && ! empty($c['settles_to_vendor_id'])
                    ? Vendor::where('company_id', $company->id)->find($c['settles_to_vendor_id']) : null;
                $accountId = $settlesTo === 'bank' ? ($c['bank_account_id'] ?? null) : ($c['clearing_account_id'] ?? null);

                return [
                    'code' => $c['code'] ?? null,
                    'label' => $c['label'] ?? $c['code'] ?? 'Card',
                    'type' => $c['type'],
                    'settles_to' => $settlesTo,
                    'vendor_id' => $vendor?->id,
                    'vendor_name' => $vendor?->name,
                    'account_id' => $settlesTo === 'supplier' ? null : $accountId,
                    'account_name' => $settlesTo === 'supplier' || ! $accountId ? null
                        : Account::where('company_id', $company->id)->whereKey($accountId)->value('name'),
                ];
            })
            ->filter(fn ($c) => $c['code'] && ($c['settles_to'] === 'supplier' ? $c['vendor_id'] : $c['account_id']))
            ->values()->all();
    }

    public function record(Company $company, array $data, User $user): CardTaxDeduction
    {
        $wht = app(WithholdingTax::class);
        if (! $wht->enabled($company)) {
            throw ValidationException::withMessages(['amount' => 'Turn on tax in company settings first.']);
        }
        $channel = collect($this->channels($company))->firstWhere('code', $data['channel_code']);
        if (! $channel) {
            throw ValidationException::withMessages(['channel_code' => 'Choose one of the station’s card channels.']);
        }
        $date = $data['deduction_date'];
        $amount = round((float) $data['amount'], 2);
        app(DocumentDateLock::class)->assertOpen($company->id, $date, 'This tax deduction');

        return AccountingWriteTransaction::run(function () use ($company, $data, $user, $channel, $date, $amount, $wht) {
            $advance = $wht->advanceTaxAccount($company);
            $currency = $company->base_currency ?: 'PKR';
            $what = "Tax deducted on {$channel['label']} sales";
            $deduction = CardTaxDeduction::create([
                'company_id' => $company->id, 'channel_code' => $channel['code'], 'channel_label' => $channel['label'],
                'settles_to' => $channel['settles_to'], 'deduction_date' => $date, 'amount' => $amount,
                'section' => $data['section'] ?? null, 'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'vendor_id' => $channel['vendor_id'], 'created_by_user_id' => $user->id,
            ]);

            if ($channel['settles_to'] === 'supplier') {
                $result = app(CommandBus::class)->dispatch('bill.create', [
                    'vendor_id' => $channel['vendor_id'], 'bill_date' => $date, 'due_date' => $date, 'status' => 'received',
                    'currency' => $currency, 'base_currency' => $currency,
                    'vendor_invoice_number' => $data['reference'] ?? null,
                    'notes' => $what.(! empty($data['notes']) ? '. '.$data['notes'] : ''),
                    'line_items' => [['description' => $what, 'quantity' => 1, 'unit_price' => $amount, 'expense_account_id' => $advance]],
                ], $user, true);
                $bill = Bill::where('company_id', $company->id)->findOrFail($result['data']['id']);
                $deduction->update(['bill_id' => $bill->id, 'transaction_id' => $bill->transaction_id]);

                return $deduction;
            }

            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id, 'transaction_type' => 'card_tax_deduction', 'date' => $date,
                'currency' => $currency, 'base_currency' => $currency, 'description' => $what,
                'reference_type' => 'fuel.card_tax_deductions', 'reference_id' => $deduction->id,
            ], [
                ['account_id' => $advance, 'type' => 'debit', 'amount' => $amount, 'description' => $what],
                ['account_id' => $channel['account_id'], 'type' => 'credit', 'amount' => $amount, 'description' => $what],
            ]);
            $deduction->update(['transaction_id' => $transaction->id]);

            return $deduction;
        });
    }

    /** Take it back: reverse the posting (or void the supplier bill) and keep the row as undone. */
    public function undo(Company $company, CardTaxDeduction $deduction, User $user): void
    {
        $today = app(CompanyClock::class)->today($company);
        app(DocumentDateLock::class)->assertOpen($company->id, $today, 'Undoing this tax deduction');

        AccountingWriteTransaction::run(function () use ($company, $deduction, $user, $today) {
            if ($deduction->bill_id) {
                $bill = Bill::where('company_id', $company->id)->lockForUpdate()->findOrFail($deduction->bill_id);
                // An advance the supplier held may have been applied to this bill automatically;
                // that was a ledger reclass only, so it goes back to being an advance.
                BillPaymentAllocation::where('company_id', $company->id)->where('bill_id', $bill->id)->delete();
                BillPaymentCreateAction::recomputeBillStatus($bill, 0);
                $bill->save();
                app(CommandBus::class)->dispatch('bill.void', ['id' => $bill->id, 'reason' => 'Card tax deduction undone', 'reversal_date' => $today], $user, true);
            } elseif ($deduction->transaction_id) {
                $transaction = Transaction::where('company_id', $company->id)->findOrFail($deduction->transaction_id);
                app(PostingService::class)->reverseTransaction($transaction, 'Card tax deduction undone', $today);
            }
            $deduction->delete();
        });
    }

    /** Recent deductions for the settlement page, newest first, undone ones included. */
    public function recent(string $companyId): array
    {
        return CardTaxDeduction::withTrashed()->where('company_id', $companyId)
            ->orderByDesc('deduction_date')->orderByDesc('created_at')->limit(24)->get()
            ->map(fn (CardTaxDeduction $d) => [
                'id' => $d->id, 'date' => $d->deduction_date->toDateString(), 'channel' => $d->channel_label,
                'amount' => round((float) $d->amount, 2), 'section' => $d->section, 'reference' => $d->reference,
                'bill_id' => $d->bill_id, 'undone' => $d->trashed(),
            ])->all();
    }

    /** Register lines (received side) for WithholdingRegisterSources: one per deduction, plus its undo. */
    public function registerLines(string $companyId, string $slug, string $from, string $to): array
    {
        $lines = [];
        $rows = CardTaxDeduction::withTrashed()->where('company_id', $companyId)
            ->where(fn ($q) => $q->whereBetween('deduction_date', [$from, $to])->orWhereNotNull('deleted_at'))->get();
        foreach ($rows as $row) {
            $line = [
                'payment_id' => $row->id, 'payment_number' => $row->reference ?: 'Card tax',
                'reference' => $row->reference, 'url' => "/{$slug}/fuel/vendor-cards/pending",
                'side' => 'received', 'party_id' => $row->vendor_id, 'party' => $row->channel_label,
                'tax_id' => null, 'filer_status' => null, 'section' => $row->section, 'rate' => null,
            ];
            $date = $row->deduction_date->toDateString();
            if ($date >= $from && $date <= $to) {
                $lines[] = $line + ['date' => $date, 'kind' => 'deduction', 'gross' => 0.0, 'wht' => round((float) $row->amount, 2)];
            }
            if ($row->trashed()) {
                $undone = $row->deleted_at->toDateString();
                if ($undone >= $from && $undone <= $to) {
                    $lines[] = $line + ['date' => $undone, 'kind' => 'reversal', 'gross' => 0.0, 'wht' => -round((float) $row->amount, 2)];
                }
            }
        }

        return $lines;
    }
}
