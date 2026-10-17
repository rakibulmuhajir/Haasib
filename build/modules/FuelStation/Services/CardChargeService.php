<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Actions\BillPayment\CreateAction as BillPaymentCreateAction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
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
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Card charges for a card set to manual charges in Station settings (charge_mode = manual): the
 * charge the bank keeps on POS swipes, or the fuel supplier on its vendor card, entered by hand
 * from the statement -- usually at month end. A card on automatic charges has its % taken at
 * every daily close instead, and is not offered here, so nothing is charged twice.
 *
 * The charge posts once, on its date, to the card-charges expense the close uses
 * (DailyCloseService::cardChargesAccountId), taken from wherever that card's money is:
 *   - settles to clearing: Dr card charges / Cr the card's clearing account (less to collect);
 *   - settles to bank:     Dr card charges / Cr the card's bank account (less arrived);
 *   - settles to supplier: a bill to that supplier for the charge -- it credited us that much less
 *     against our fuel bills, so we owe it that much more.
 *
 * It covers a period (default: the month of its date). Charge / the card's sales over the period's
 * daily closes is its rate, and each day's card sales -- and each supplier bill a vendor card's
 * money paid -- shows its share at that rate. Display only: no closed day is rewritten.
 */
class CardChargeService
{
    public const TYPES = ['card_pos', 'fuel_card'];

    /** The station's enabled card channels, with how each is charged and where its charge comes from. */
    public function channels(Company $company, bool $manualOnly = false): array
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
                    'charge_mode' => ($c['charge_mode'] ?? 'automatic') === 'manual' ? 'manual' : 'automatic',
                    'settles_to' => $settlesTo,
                    'vendor_id' => $vendor?->id,
                    'vendor_name' => $vendor?->name,
                    'clearing_account_id' => $c['clearing_account_id'] ?? null,
                    'account_id' => $settlesTo === 'supplier' ? null : $accountId,
                    'account_name' => $settlesTo === 'supplier' || ! $accountId ? null
                        : Account::where('company_id', $company->id)->whereKey($accountId)->value('name'),
                ];
            })
            ->filter(fn ($c) => $c['code'] && ($c['settles_to'] === 'supplier' ? $c['vendor_id'] : $c['account_id']))
            ->filter(fn ($c) => ! $manualOnly || $c['charge_mode'] === 'manual')
            ->values()->all();
    }

    public function record(Company $company, array $data, User $user): CardCharge
    {
        $channel = collect($this->channels($company, true))->firstWhere('code', $data['channel_code']);
        if (! $channel) {
            throw ValidationException::withMessages(['channel_code' => 'Choose a card set to manual charges.']);
        }
        $date = $data['charge_date'];
        $from = $data['period_from'] ?? Carbon::parse($date)->startOfMonth()->toDateString();
        $to = $data['period_to'] ?? Carbon::parse($date)->endOfMonth()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['period_to' => 'The period ends before it starts.']);
        }
        $amount = round((float) $data['amount'], 2);
        app(DocumentDateLock::class)->assertOpen($company->id, $date, 'This card charge');
        $expense = app(DailyCloseService::class)->cardChargesAccountId($company->id);

        return AccountingWriteTransaction::run(function () use ($company, $data, $user, $channel, $date, $from, $to, $amount, $expense) {
            $currency = $company->base_currency ?: 'PKR';
            $what = "{$channel['label']} charges, {$from} to {$to}";
            $charge = CardCharge::create([
                'company_id' => $company->id, 'channel_code' => $channel['code'], 'channel_label' => $channel['label'],
                'settles_to' => $channel['settles_to'], 'charge_date' => $date, 'period_from' => $from, 'period_to' => $to,
                'amount' => $amount, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
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

    /** A card's sales over the posted daily closes in a period, from each close's own record of them. */
    public function cardSales(string $companyId, string $channelCode, string $from, string $to): float
    {
        return round(Transaction::where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')->whereBetween('transaction_date', [$from, $to])->get(['id', 'metadata'])
            ->sum(fn (Transaction $close) => collect($close->metadata['payment_receipt_postings'] ?? [])
                ->where('channel_code', $channelCode)->sum(fn ($p) => (float) ($p['amount'] ?? 0))), 2);
    }

    /** What share of its card's sales a charge is, as a %; null while the period has no card sales. */
    public function rate(CardCharge $charge, ?float $sales = null): ?float
    {
        $sales ??= $this->cardSales($charge->company_id, $charge->channel_code, $this->from($charge), $this->to($charge));

        return $sales > 0 ? round((float) $charge->amount / $sales * 100, 4) : null;
    }

    /** Recent charges for the settlement page, newest first, undone ones included. */
    public function recent(string $companyId): array
    {
        return CardCharge::withTrashed()->where('company_id', $companyId)
            ->orderByDesc('charge_date')->orderByDesc('created_at')->limit(24)->get()
            ->map(function (CardCharge $c) {
                $sales = $this->cardSales($c->company_id, $c->channel_code, $this->from($c), $this->to($c));

                return [
                    'id' => $c->id, 'date' => $c->charge_date->toDateString(), 'channel' => $c->channel_label,
                    'period_from' => $this->from($c), 'period_to' => $this->to($c),
                    'amount' => round((float) $c->amount, 2), 'sales' => $sales, 'rate' => $this->rate($c, $sales),
                    'reference' => $c->reference, 'bill_id' => $c->bill_id, 'undone' => $c->trashed(),
                ];
            })->all();
    }

    /**
     * For a daily close's page: the rate of each manual charge whose period covers the day, by card
     * code, and the charges booked on the day itself.
     *
     * @return array{rates: array<string, array<string, mixed>>, booked: list<array<string, mixed>>}
     */
    public function forDay(string $companyId, string $date): array
    {
        $rates = [];
        $covering = CardCharge::where('company_id', $companyId)
            ->where(fn ($q) => $q->where('period_from', '<=', $date)->where('period_to', '>=', $date))->get();
        foreach ($covering as $charge) {
            $rate = $this->rate($charge);
            if ($rate !== null) {
                // Two charges over the same day (say a monthly fee and a correction) add up.
                $rates[$charge->channel_code] = ['rate' => round(($rates[$charge->channel_code]['rate'] ?? 0) + $rate, 4),
                    'period_from' => $this->from($charge), 'period_to' => $this->to($charge)];
            }
        }

        $booked = CardCharge::where('company_id', $companyId)->whereDate('charge_date', $date)->get()
            ->map(fn (CardCharge $c) => ['channel' => $c->channel_label, 'amount' => round((float) $c->amount, 2),
                'period_from' => $this->from($c), 'period_to' => $this->to($c), 'rate' => $this->rate($c), 'reference' => $c->reference])
            ->all();

        return ['rates' => $rates, 'booked' => $booked];
    }

    /**
     * For a supplier bill's page: the share of each vendor-card charge carried by the card money
     * that paid this bill (the close's card settlements to that supplier, within the charge's period).
     */
    public function billNotes(Bill $bill): array
    {
        $charges = CardCharge::where('company_id', $bill->company_id)->where('vendor_id', $bill->vendor_id)
            ->where('settles_to', 'supplier')->get();
        if ($charges->isEmpty()) {
            return [];
        }
        $company = Company::find($bill->company_id);
        $clearing = collect($this->channels($company))->pluck('clearing_account_id', 'code');

        $notes = [];
        foreach ($charges as $charge) {
            $account = $clearing[$charge->channel_code] ?? null;
            if (! $account || ! ($rate = $this->rate($charge))) {
                continue;
            }
            $paid = (float) BillPaymentAllocation::where('company_id', $bill->company_id)->where('bill_id', $bill->id)
                ->whereIn('bill_payment_id', BillPayment::where('company_id', $bill->company_id)->where('vendor_id', $bill->vendor_id)
                    ->where('payment_account_id', $account)->whereBetween('payment_date', [$this->from($charge), $this->to($charge)])->select('id'))
                ->sum('amount_allocated');
            if ($paid > 0.004) {
                $notes[] = [
                    'label' => "{$charge->channel_label} charge",
                    'detail' => rtrim(rtrim(number_format($rate, 2), '0'), '.')."% of ".number_format($paid, 0)." paid by card, {$this->from($charge)} to {$this->to($charge)}",
                    'amount' => round($paid * $rate / 100, 2),
                ];
            }
        }

        return $notes;
    }

    private function from(CardCharge $charge): string
    {
        return $charge->period_from ? Carbon::parse($charge->period_from)->toDateString() : $charge->charge_date->copy()->startOfMonth()->toDateString();
    }

    private function to(CardCharge $charge): string
    {
        return $charge->period_to ? Carbon::parse($charge->period_to)->toDateString() : $charge->charge_date->copy()->endOfMonth()->toDateString();
    }
}
