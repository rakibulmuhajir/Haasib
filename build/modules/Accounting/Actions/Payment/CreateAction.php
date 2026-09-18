<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PaymentAllocationService;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Records one cash/bank movement (one Payment, one journal) and settles it against a
 * buyer's invoices. Three ways to say what it settles, tried in this order:
 *
 *  1. `allocations`: [{invoice_id, amount}, ...] - settle exactly these invoices for
 *     exactly these amounts (the caller picked several invoices by hand).
 *  2. `invoice`: a single invoice identifier (legacy: still how a standalone /payments
 *     submission and a Daily Close row with one invoice selected both call this). Settles
 *     that one invoice up to its balance.
 *  3. Neither given, just `customer_id`: auto-allocate across that buyer's open invoices,
 *     oldest invoice_date first (ties by invoice_number), until the invoices or the amount
 *     run out.
 *
 * Whatever is left over after allocation - including the whole amount when the buyer has
 * no open invoice at all - is not an error. It is recorded as an on-account credit: a
 * payment_allocations row with no invoice_id (see PaymentAllocation and the migration that
 * made invoice_id nullable). The buyer's balance drops by the full payment amount either
 * way, because CustomerStatementService's running balance is driven by Payment.amount, not
 * by how much of it happened to land on an invoice yet.
 */
class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'invoice' => 'nullable|string|max:255',
            'invoice_ids' => 'nullable|array',
            'invoice_ids.*' => 'string',
            'customer_id' => 'nullable|uuid',
            'allocations' => 'nullable|array',
            'allocations.*.invoice_id' => 'required_with:allocations|string',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0.01',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'transaction_charge' => 'nullable|numeric|min:0|max:999999999.99',
            'method' => 'required|string|in:cash,check,card,bank_transfer,other',
            'currency' => 'nullable|string|size:3|uppercase', // must match invoice currency or base
            'exchange_rate' => 'nullable|numeric|min:0.00000001|max:999999999',
            'date' => 'nullable|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'deposit_account_id' => 'required|uuid',
            'ar_account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_CREATE;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $amount = (float) $params['amount'];
        $transactionCharge = round((float) ($params['transaction_charge'] ?? 0), 6);
        if ($transactionCharge > $amount) {
            throw new \InvalidArgumentException('Transaction charge cannot exceed the payment amount.');
        }

        [$customer, $plan] = $this->buildAllocationPlan($params, $company->id, $amount);

        $paymentDate = !empty($params['date']) ? Carbon::parse($params['date']) : now();
        // Default currency: the first allocated invoice's currency (matches the historical
        // single-invoice behaviour exactly when there is one), else the buyer's/company's
        // base currency for a purely on-account payment with nothing to default from.
        $currency = strtoupper($params['currency'] ?? ($plan[0]['invoice']->currency ?? null) ?? $customer?->base_currency ?? $company->base_currency);
        $baseCurrency = $company->base_currency ?? $currency;
        $exchangeRate = $currency === $baseCurrency ? null : ($params['exchange_rate'] ?? $plan[0]['invoice']->exchange_rate ?? null);
        if ($currency !== $baseCurrency && $exchangeRate === null) {
            throw new \InvalidArgumentException('exchange_rate is required when payment currency differs from base_currency.');
        }

        // Per-invoice currency compatibility, preserved exactly as the single-invoice path
        // always enforced it, just checked once per allocated invoice instead of once total.
        foreach ($plan as $row) {
            $invoice = $row['invoice'];
            if ($currency !== $invoice->currency && $currency !== $invoice->base_currency) {
                throw ValidationException::withMessages([
                    'currency' => "Payment currency must match invoice {$invoice->invoice_number}'s currency or base currency.",
                ]);
            }
        }

        $paymentNumber = Payment::generatePaymentNumber($company->id);
        $baseAmount = $currency === $baseCurrency ? round($amount, 2) : round($amount * ($exchangeRate ?? 1), 2);
        $baseTransactionCharge = round($transactionCharge * ($exchangeRate ?? 1), 2);

        $payment = Payment::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency' => $baseCurrency,
            'base_amount' => $baseAmount,
            'transaction_charge' => $transactionCharge,
            'base_transaction_charge' => $baseTransactionCharge,
            'payment_method' => $params['method'] ?? 'bank_transfer',
            'deposit_account_id' => $params['deposit_account_id'] ?? null,
            'reference_number' => $params['reference'] ?? null,
            'notes' => $params['notes'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        $allocationService = app(PaymentAllocationService::class);
        $settled = [];
        $allocatedTotal = 0.0;
        foreach ($plan as $row) {
            $invoice = $row['invoice'];
            $allocatedAmount = round((float) $row['amount'], 6);
            if ($allocatedAmount <= 0) {
                continue;
            }
            $allocatedTotal = round($allocatedTotal + $allocatedAmount, 6);

            $invoiceAmount = $currency === $invoice->currency
                ? $allocatedAmount
                : round($allocatedAmount / (float) ($invoice->exchange_rate ?: 1), 6);
            $baseAmountAllocated = $currency === $baseCurrency
                ? round($allocatedAmount, 2)
                : round($allocatedAmount * ($exchangeRate ?? 1), 2);

            PaymentAllocation::create([
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount_allocated' => $invoiceAmount,
                'base_amount_allocated' => $baseAmountAllocated,
                'applied_at' => $paymentDate,
            ]);

            $result = $allocationService->settleInvoice($invoice, $invoiceAmount);
            $settled[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoiceAmount,
                'status' => $result['status'],
                'balance' => $result['balance'],
            ];
        }

        // Whatever the plan did not reach - including the full amount when there was
        // nothing to allocate to - sits on the buyer's account unapplied. Recording it as
        // its own null-invoice allocation (rather than, say, skipping it) is what keeps
        // sum(payment_allocations for this payment) == payment.amount, an invariant
        // PostingService::postPayment already relies on to post the payment at all.
        $onAccount = round($amount - $allocatedTotal, 6);
        if ($onAccount > 0.000001) {
            PaymentAllocation::create([
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'invoice_id' => null,
                'amount_allocated' => $onAccount,
                'base_amount_allocated' => $currency === $baseCurrency
                    ? round($onAccount, 2)
                    : round($onAccount * ($exchangeRate ?? 1), 2),
                'applied_at' => $paymentDate,
            ]);
        }

        // Post to GL
        $postingService = app(GlPostingService::class);
        $arAccountId = $params['ar_account_id']
            ?? $customer->ar_account_id
            ?? $company->ar_account_id;
        if (!$arAccountId) {
            throw new \RuntimeException('AR account is required to post the payment.');
        }
        if (empty($params['deposit_account_id'])) {
            throw new \RuntimeException('Deposit account is required to post the payment.');
        }

        $transaction = $postingService->postPayment($payment, $params['deposit_account_id'], $arAccountId);
        $payment->transaction_id = $transaction->id;
        $payment->save();

        $summary = count($settled) === 1
            ? "on {$settled[0]['invoice_number']}"
            : (count($settled) > 1 ? 'across ' . count($settled) . ' invoices' : 'on account');
        $onAccountMsg = $onAccount > 0.000001
            ? ' (' . PaletteFormatter::money($onAccount, $currency) . ' left on account)'
            : '';

        return [
            'message' => "Payment recorded: " .
                PaletteFormatter::money($amount, $currency) .
                " {$summary}{$onAccountMsg}",
            'data' => [
                'id' => $payment->id,
                'customer_id' => $customer->id,
                'amount' => PaletteFormatter::money($amount, $currency),
                'on_account' => round($onAccount, 2),
                'allocations' => $settled,
                // Backward-compatible single-invoice fields, populated when the payment
                // settled exactly one invoice (the historical shape every existing caller
                // reads $result['data']['invoice']/['balance']/['status'] from).
                'invoice' => $settled[0]['invoice_number'] ?? null,
                'balance' => isset($settled[0]) ? PaletteFormatter::money($settled[0]['balance'], $currency) : null,
                'status' => $settled[0]['status'] ?? null,
            ],
        ];
    }

    /**
     * @return array{0: Customer, 1: array<int, array{invoice: Invoice, amount: float}>}
     */
    private function buildAllocationPlan(array $params, string $companyId, float $amount): array
    {
        $allocationService = app(PaymentAllocationService::class);

        if (!empty($params['allocations'])) {
            $customer = null;
            $rows = [];
            foreach ($params['allocations'] as $i => $alloc) {
                $invoice = $this->resolveInvoiceOrFail($alloc['invoice_id'], $companyId);
                $this->assertInvoiceIsSettleable($invoice);
                if ($customer === null) {
                    $customer = $invoice->customer;
                } elseif ($invoice->customer_id !== $customer->id) {
                    throw ValidationException::withMessages([
                        "allocations.{$i}.invoice_id" => "Invoice {$invoice->invoice_number} belongs to a different buyer.",
                    ]);
                }
                $rows[] = ['invoice' => $invoice, 'amount' => round((float) $alloc['amount'], 6)];
            }
            if (!empty($params['customer_id']) && $customer && $params['customer_id'] !== $customer->id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Allocations belong to a different buyer than customer_id.',
                ]);
            }
            $sum = round(array_sum(array_column($rows, 'amount')), 2);
            if ($sum > round($amount, 2) + 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => "Allocations ({$sum}) exceed the payment amount ({$amount}).",
                ]);
            }
            if (!$customer) {
                throw ValidationException::withMessages(['allocations' => 'At least one allocation is required.']);
            }

            return [$customer, $rows];
        }

        if (!empty($params['invoice'])) {
            $invoice = $this->resolveInvoiceOrFail($params['invoice'], $companyId);
            $this->assertInvoiceIsSettleable($invoice);
            if (!empty($params['customer_id']) && $params['customer_id'] !== $invoice->customer_id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'customer_id does not match the invoice\'s buyer.',
                ]);
            }
            $currency = strtoupper($params['currency'] ?? $invoice->currency);

            return [$invoice->customer, $this->autoAllocate(collect([$invoice]), $currency, $amount)];
        }

        if (empty($params['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => 'A buyer, invoice, or allocation set is required to record a payment.',
            ]);
        }

        $customer = Customer::where('company_id', $companyId)->find($params['customer_id']);
        if (!$customer) {
            throw ValidationException::withMessages(['customer_id' => 'Choose a buyer belonging to this company.']);
        }

        // `invoice_ids`: the buyer picked several invoices by hand but left it to us to work
        // out how much of the payment goes to each (still oldest-first); no ids at all is a
        // full auto-allocation across every open invoice, or a pure on-account payment when
        // the buyer has none.
        if (!empty($params['invoice_ids'])) {
            $chosen = collect($params['invoice_ids'])->map(function ($id) use ($companyId, $customer) {
                $invoice = $this->resolveInvoiceOrFail($id, $companyId);
                $this->assertInvoiceIsSettleable($invoice);
                if ($invoice->customer_id !== $customer->id) {
                    throw ValidationException::withMessages([
                        'invoice_ids' => "Invoice {$invoice->invoice_number} belongs to a different buyer.",
                    ]);
                }
                return $invoice;
            })->sortBy([['invoice_date', 'asc'], ['invoice_number', 'asc']])->values();
            $currency = strtoupper($params['currency'] ?? $chosen->first()?->currency ?? $customer->base_currency);

            return [$customer, $this->autoAllocate($chosen, $currency, $amount)];
        }

        $open = $allocationService->openInvoicesOldestFirst($companyId, $customer->id);
        $currency = strtoupper($params['currency'] ?? $open->first()?->currency ?? $customer->base_currency ?? 'USD');

        return [$customer, $this->autoAllocate($open, $currency, $amount)];
    }

    /**
     * Greedily fills invoices in the order given (already oldest-first) until the amount is
     * exhausted; whatever does not fit stays unallocated, which the caller records on
     * account rather than treating as an error.
     *
     * @param \Illuminate\Support\Collection<int, Invoice> $invoices
     * @return array<int, array{invoice: Invoice, amount: float}>
     */
    private function autoAllocate($invoices, string $currency, float $amount): array
    {
        $remaining = $amount;
        $rows = [];
        foreach ($invoices as $invoice) {
            if ($remaining <= 0.000001) {
                break;
            }
            $capacity = $this->invoiceBalanceInCurrency($invoice, $currency);
            $take = round(min($remaining, $capacity), 6);
            if ($take <= 0) {
                continue;
            }
            $rows[] = ['invoice' => $invoice, 'amount' => $take];
            $remaining = round($remaining - $take, 6);
        }

        return $rows;
    }

    private function assertInvoiceIsSettleable(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled' || $invoice->status === 'void') {
            throw ValidationException::withMessages(['invoice' => "Cannot record payment on cancelled invoice {$invoice->invoice_number}."]);
        }
        if ($invoice->status === 'draft') {
            throw ValidationException::withMessages(['invoice' => "Cannot record payment on draft invoice {$invoice->invoice_number}. Send it first."]);
        }
        if ($invoice->status === 'paid' || (float) $invoice->balance <= 0) {
            throw ValidationException::withMessages(['invoice' => "Invoice {$invoice->invoice_number} is already fully paid."]);
        }
    }

    /**
     * The inverse of the invoiceAmount conversion below: how much of this invoice's
     * remaining balance, expressed in the payment's currency, is available to allocate.
     */
    private function invoiceBalanceInCurrency(Invoice $invoice, string $currency): float
    {
        return $currency === $invoice->currency
            ? (float) $invoice->balance
            : round((float) $invoice->balance * (float) ($invoice->exchange_rate ?: 1), 6);
    }

    private function resolveInvoiceOrFail(string $identifier, string $companyId): Invoice
    {
        try {
            return $this->resolveInvoice($identifier, $companyId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw ValidationException::withMessages(['invoice' => "Invoice not found: {$identifier}"]);
        }
    }

    private function resolveInvoice(string $identifier, string $companyId): Invoice
    {
        // Try UUID
        if (Str::isUuid($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($invoice) return $invoice;
        }

        // Try invoice number (exact)
        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', $identifier)
            ->first();
        if ($invoice) return $invoice;

        // Try partial number match (e.g., "00001" matches "INV-2024-00001")
        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', "%{$identifier}")
            ->first();
        if ($invoice) return $invoice;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Invoice not found: {$identifier}");
    }
}
