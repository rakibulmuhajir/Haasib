<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerPeriodDiscountService
{
    public function preview(Company $company, Customer $customer, array $data, bool $lock = false): array
    {
        $from = $data['from'];
        $to = $data['to'];
        $method = $data['method'] ?? 'fixed';
        $value = (float) ($data['value'] ?? 0);
        $covered = CreditNote::where('company_id', $company->id)->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])->whereNotNull('period_discount')
            ->where('period_discount->from', '<=', $to)->where('period_discount->to', '>=', $from)->orderBy('id')->get();
        $overlap = $covered->first(fn ($credit) => $credit->period_discount['from'] <= $to && $credit->period_discount['to'] >= $from);

        $query = Invoice::where('company_id', $company->id)->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])->withoutCorrectionArtifacts()
            ->whereBetween('invoice_date', [$from, $to])->orderBy('invoice_date')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $invoices = $query->get(['id', 'invoice_number', 'invoice_date', 'currency', 'discount_amount', 'tax_amount', 'total_amount', 'balance']);
        // Aggregate quantities in PostgreSQL rather than hydrating every invoice's line items.
        $fuelTotals = DB::table('acct.invoice_line_items as line')
            ->join('inv.items as item', 'item.id', '=', 'line.item_id')
            ->join('acct.invoices as invoice', 'invoice.id', '=', 'line.invoice_id')
            ->where('line.company_id', $company->id)->where('item.company_id', $company->id)->where('invoice.company_id', $company->id)
            ->where('invoice.customer_id', $customer->id)->whereBetween('invoice.invoice_date', [$from, $to])
            ->whereNull('line.deleted_at')->whereNotNull('item.fuel_category')
            ->selectRaw('line.invoice_id, SUM(line.quantity) as litres, SUM(line.line_total) as gross')
            ->groupBy('line.invoice_id')->get()->keyBy('invoice_id');
        $credited = CreditNote::where('company_id', $company->id)->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])->whereNotNull('invoice_id')->pluck('invoice_id')->all();
        $coveredIds = $covered->flatMap(fn ($credit) => $credit->period_discount['invoice_ids'])->all();
        $opening = $company->settings['opening_balances'] ?? [];
        $openingIds = array_merge($opening['invoice_ids'] ?? [], $opening['retired_invoice_ids'] ?? []);
        $sources = [];
        foreach ($invoices as $invoice) {
            if ($invoice->currency !== $company->base_currency || (float) $invoice->discount_amount > 0
                || (float) $invoice->tax_amount > 0 || in_array($invoice->id, $credited, true) || in_array($invoice->id, $coveredIds, true)
                || in_array($invoice->id, $openingIds, true)) {
                continue;
            }
            $fuel = $fuelTotals->get($invoice->id);
            $litres = round((float) ($fuel?->litres ?? 0), 2);
            if ($method === 'per_litre' && $litres <= 0) {
                continue;
            }
            $gross = round($method === 'per_litre' ? (float) ($fuel?->gross ?? 0) : (float) $invoice->total_amount, 2);
            if ($gross <= 0) {
                continue;
            }
            $sources[] = ['id' => $invoice->id, 'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->toDateString(), 'gross' => $gross,
                'balance' => round((float) $invoice->balance, 6), 'litres' => $litres];
        }

        $gross = round(array_sum(array_column($sources, 'gross')), 2);
        $litres = round(array_sum(array_column($sources, 'litres')), 2);
        $amount = round(match ($method) {
            'percent' => $gross * $value / 100,
            'per_litre' => $litres * $value,
            default => $value,
        }, 2);
        $open = round(array_sum(array_map(fn ($source) => $this->creditCapacity($source['balance']), $sources)), 2);
        $snapshot = ['from' => $from, 'to' => $to, 'method' => $method, 'value' => $value,
            'eligible_amount' => $gross, 'litres' => $litres, 'invoices' => $sources,
            'invoice_ids' => array_column($sources, 'id')];
        $snapshot['token'] = hash('sha256', json_encode([$company->id, $customer->id, $snapshot, $covered->pluck('id')->all()]));
        $error = $overlap ? "This period overlaps {$overlap->credit_note_number}. Void that discount before replacing it."
            : ($sources === [] ? 'No eligible sales in this period.'
                : ($amount > $gross ? 'The discount cannot exceed eligible sales.' : null));

        return ['snapshot' => $snapshot, 'amount' => $amount, 'invoice_count' => count($sources),
            'excluded_count' => $invoices->count() - count($sources), 'applied' => min($open, $amount),
            'remaining_credit' => max(0, round($amount - $open, 2)), 'error' => $error];
    }

    public function post(Company $company, string $customerId, array $data, string $userId): CreditNote
    {
        return AccountingWriteTransaction::run(function () use ($company, $customerId, $data, $userId) {
            // Serializes numbering and competing period credits; every source balance is also locked.
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::where('company_id', $company->id)->lockForUpdate()->findOrFail($customerId);
            $preview = $this->preview($company, $customer, $data, true);
            if ($preview['error']) {
                throw ValidationException::withMessages(['period' => $preview['error']]);
            }
            if (! hash_equals($preview['snapshot']['token'], (string) ($data['preview_token'] ?? ''))) {
                throw ValidationException::withMessages(['period' => 'Sales or balances changed. Preview the discount again before posting.']);
            }
            if ($preview['amount'] <= 0) {
                throw ValidationException::withMessages(['value' => 'Enter a positive discount.']);
            }
            if ($data['credit_date'] < $data['to']) {
                throw ValidationException::withMessages(['credit_date' => 'The posting date cannot be before the period ends.']);
            }
            app(DocumentDateLock::class)->assertOpen($company->id, $data['credit_date'], 'This period discount');
            $discountAccount = app(SalesDiscountAccountResolver::class)->resolve($company);
            $ar = Account::where('company_id', $company->id)->where('is_active', true)
                ->where('subtype', 'accounts_receivable')->find($customer->ar_account_id ?: $company->ar_account_id);
            if (! $ar || ($ar->currency && $ar->currency !== $company->base_currency)) {
                Log::warning('period_discount_customer_setup_invalid', ['company_id' => $company->id, 'customer_id' => $customer->id, 'account_id' => $customer->ar_account_id ?: $company->ar_account_id]);
                throw ValidationException::withMessages(['period' => 'This customer’s payment setup needs attention. Ask your company administrator to review it, then try again.']);
            }
            $reason = 'Period discount · '.$data['from'].' to '.$data['to'];
            $credit = CreditNote::create([
                'company_id' => $company->id, 'customer_id' => $customer->id,
                'credit_note_number' => CreditNote::generateCreditNoteNumber($company->id),
                'credit_date' => $data['credit_date'], 'amount' => $preview['amount'],
                'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'base_amount' => $preview['amount'], 'exchange_rate' => null,
                'reason' => $reason, 'notes' => $data['notes'] ?? null, 'status' => 'issued',
                'period_discount' => $preview['snapshot'], 'created_by_user_id' => $userId,
            ]);
            $credit->items()->create(['company_id' => $company->id, 'line_number' => 1,
                'description' => $reason, 'quantity' => 1, 'unit_price' => $preview['amount'],
                'line_total' => $preview['amount'], 'total' => $preview['amount'], 'created_by_user_id' => $userId]);
            $journal = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id, 'transaction_type' => 'credit_note', 'date' => $data['credit_date'],
                'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'reference_type' => 'acct.credit_notes', 'reference_id' => $credit->id, 'description' => $reason,
            ], [
                ['account_id' => $discountAccount->id, 'type' => 'debit', 'amount' => $preview['amount'], 'description' => $reason],
                ['account_id' => $ar->id, 'type' => 'credit', 'amount' => $preview['amount'], 'description' => $customer->name],
            ]);
            $credit->update(['transaction_id' => $journal->id, 'posted_at' => now()]);
            $left = $preview['amount'];
            foreach ($preview['snapshot']['invoices'] as $source) {
                $applied = min($left, $this->creditCapacity($source['balance']));
                if ($applied <= 0) {
                    continue;
                }
                $invoice = Invoice::where('company_id', $company->id)->findOrFail($source['id']);
                $before = (float) $invoice->balance;
                $settlement = app(PaymentAllocationService::class)->settleInvoice($invoice, $applied, $data['credit_date']);
                $after = $settlement['balance'];
                CreditNoteApplication::create(['company_id' => $company->id, 'credit_note_id' => $credit->id,
                    'invoice_id' => $invoice->id, 'amount_applied' => $applied, 'applied_at' => $data['credit_date'],
                    'user_id' => $userId, 'invoice_balance_before' => $before, 'invoice_balance_after' => $after]);
                $left = round($left - $applied, 2);
            }
            if ($left <= 0) {
                $credit->update(['status' => 'applied']);
            }

            return $credit;
        });
    }

    public function void(CreditNote $credit, ?string $reason): array
    {
        return AccountingWriteTransaction::run(function () use ($credit, $reason) {
            Company::whereKey($credit->company_id)->lockForUpdate()->firstOrFail();
            $credit = CreditNote::where('company_id', $credit->company_id)->lockForUpdate()->findOrFail($credit->id);
            if ($credit->status === 'void') {
                throw ValidationException::withMessages(['credit_note' => 'This period discount is already void.']);
            }
            app(DocumentDateLock::class)->assertOpen($credit->company_id, $credit->credit_date->toDateString(), 'This period discount');
            foreach ($credit->applications()->orderBy('invoice_id')->get() as $application) {
                $invoice = Invoice::where('company_id', $credit->company_id)->lockForUpdate()->findOrFail($application->invoice_id);
                app(PaymentAllocationService::class)->unapplyInvoice($invoice, (float) $application->amount_applied);
            }
            $credit->applications()->delete();
            $journal = Transaction::where('company_id', $credit->company_id)->findOrFail($credit->transaction_id);
            app(PostingService::class)->reverseTransaction($journal, $reason, $credit->credit_date);
            $credit->update(['status' => 'void', 'voided_at' => now(), 'cancellation_reason' => $reason]);

            return ['message' => "Period discount {$credit->credit_note_number} voided.", 'data' => ['id' => $credit->id]];
        });
    }

    /** Credit applications store whole cents; never apply more than the exact saved balance. */
    private function creditCapacity(float $balance): float
    {
        return max(0, floor(round($balance * 100, 4)) / 100);
    }
}
