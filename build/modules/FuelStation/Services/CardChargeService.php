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
use App\Modules\FuelStation\Models\CardCharge;
use App\Modules\FuelStation\Models\StationSettings;
use App\Services\AccountingWriteTransaction;
use App\Services\CommandBus;
use Illuminate\Validation\ValidationException;

/**
 * The charge the bank keeps on POS swipes, or the fuel supplier on its vendor card, entered by hand
 * on any date -- usually month end, from the statement. The daily close can instead take a fixed %
 * per channel (fee_percent in Station settings); a channel charged that way should have no hand
 * entries, and one entered by hand should have no %. Either way the charge is the same expense
 * (DailyCloseService::cardChargesAccountId), taken from wherever that card's money is:
 *   - settles to clearing: Dr card charges / Cr the channel's clearing account (less to collect);
 *   - settles to bank:     Dr card charges / Cr the channel's bank account (less arrived);
 *   - settles to supplier: a bill to that supplier for the charge -- it credited us that much less
 *     against our fuel bills, so we owe it that much more.
 */
class CardChargeService
{
    public const TYPES = ['card_pos', 'fuel_card'];

    /** The station's enabled card channels, with where each one's charge would come from. */
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
                    // A channel already charged a % at every daily close: a hand entry would charge twice.
                    'daily_percent' => (float) ($c['fee_percent'] ?? 0) > 0 ? (float) $c['fee_percent'] : null,
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

    public function record(Company $company, array $data, User $user): CardCharge
    {
        $channel = collect($this->channels($company))->firstWhere('code', $data['channel_code']);
        if (! $channel) {
            throw ValidationException::withMessages(['channel_code' => 'Choose one of the station’s card channels.']);
        }
        $date = $data['charge_date'];
        $amount = round((float) $data['amount'], 2);
        app(DocumentDateLock::class)->assertOpen($company->id, $date, 'This card charge');
        $expense = app(DailyCloseService::class)->cardChargesAccountId($company->id);

        return AccountingWriteTransaction::run(function () use ($company, $data, $user, $channel, $date, $amount, $expense) {
            $currency = $company->base_currency ?: 'PKR';
            $what = "{$channel['label']} charges";
            $charge = CardCharge::create([
                'company_id' => $company->id, 'channel_code' => $channel['code'], 'channel_label' => $channel['label'],
                'settles_to' => $channel['settles_to'], 'charge_date' => $date, 'amount' => $amount,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'vendor_id' => $channel['vendor_id'], 'created_by_user_id' => $user->id,
            ]);

            if ($channel['settles_to'] === 'supplier') {
                $result = app(CommandBus::class)->dispatch('bill.create', [
                    'vendor_id' => $channel['vendor_id'], 'bill_date' => $date, 'due_date' => $date, 'status' => 'received',
                    'currency' => $currency, 'base_currency' => $currency,
                    'vendor_invoice_number' => $data['reference'] ?? null,
                    'notes' => $what.(! empty($data['notes']) ? '. '.$data['notes'] : ''),
                    'line_items' => [['description' => $what, 'quantity' => 1, 'unit_price' => $amount, 'expense_account_id' => $expense]],
                ], $user, true);
                $bill = Bill::where('company_id', $company->id)->findOrFail($result['data']['id']);
                $charge->update(['bill_id' => $bill->id, 'transaction_id' => $bill->transaction_id]);

                return $charge;
            }

            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id, 'transaction_type' => 'card_charge', 'date' => $date,
                'currency' => $currency, 'base_currency' => $currency, 'description' => $what,
                'reference_type' => 'fuel.card_charges', 'reference_id' => $charge->id,
            ], [
                ['account_id' => $expense, 'type' => 'debit', 'amount' => $amount, 'description' => $what],
                ['account_id' => $channel['account_id'], 'type' => 'credit', 'amount' => $amount, 'description' => $what],
            ]);
            $charge->update(['transaction_id' => $transaction->id]);

            return $charge;
        });
    }

    /** Take it back on its own date (a mistaken entry cancels within its month): reverse the posting, or void the supplier bill, and keep the row as undone. */
    public function undo(Company $company, CardCharge $charge, User $user): void
    {
        $on = $charge->charge_date->toDateString();
        app(DocumentDateLock::class)->assertOpen($company->id, $on, 'Undoing this card charge');

        AccountingWriteTransaction::run(function () use ($company, $charge, $user, $on) {
            if ($charge->bill_id) {
                $bill = Bill::where('company_id', $company->id)->lockForUpdate()->findOrFail($charge->bill_id);
                // An advance the supplier held may have been applied to this bill automatically;
                // that was a ledger reclass only, so it goes back to being an advance.
                BillPaymentAllocation::where('company_id', $company->id)->where('bill_id', $bill->id)->delete();
                BillPaymentCreateAction::recomputeBillStatus($bill, 0);
                $bill->save();
                app(CommandBus::class)->dispatch('bill.void', ['id' => $bill->id, 'reason' => 'Card charge undone', 'reversal_date' => $on], $user, true);
            } elseif ($charge->transaction_id) {
                $transaction = Transaction::where('company_id', $company->id)->findOrFail($charge->transaction_id);
                app(PostingService::class)->reverseTransaction($transaction, 'Card charge undone', $on);
            }
            $charge->delete();
        });
    }

    /** Recent charges for the settlement page, newest first, undone ones included. */
    public function recent(string $companyId): array
    {
        return CardCharge::withTrashed()->where('company_id', $companyId)
            ->orderByDesc('charge_date')->orderByDesc('created_at')->limit(24)->get()
            ->map(fn (CardCharge $c) => [
                'id' => $c->id, 'date' => $c->charge_date->toDateString(), 'channel' => $c->channel_label,
                'amount' => round((float) $c->amount, 2), 'reference' => $c->reference,
                'bill_id' => $c->bill_id, 'undone' => $c->trashed(),
            ])->all();
    }
}
