<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'invoice' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'method' => 'required|string|in:cash,check,card,bank_transfer,other',
            'currency' => 'nullable|string|size:3|uppercase', // must match invoice currency or base
            'exchange_rate' => 'nullable|numeric|min:0.00000001|max:999999999',
            'date' => 'nullable|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $invoice = $this->resolveInvoice($params['invoice'], $company->id);

        // Validate invoice status
        if ($invoice->status === 'cancelled') {
            throw new \Exception("Cannot record payment on cancelled invoice");
        }

        if ($invoice->status === 'draft') {
            throw new \Exception("Cannot record payment on draft invoice. Send it first.");
        }

        if ($invoice->status === 'paid') {
            throw new \Exception("Invoice is already fully paid");
        }

        $amount = (float) $params['amount'];

        // Warn if overpaying
        if ($amount > $invoice->balance) {
            throw new \Exception(
                "Payment amount ({$amount}) exceeds balance due " .
                "(" . PaletteFormatter::money($invoice->balance, $invoice->currency) . "). " .
                "Maximum payment: " . PaletteFormatter::money($invoice->balance, $invoice->currency)
            );
        }

        return DB::transaction(function () use ($params, $company, $invoice, $amount) {
            $paymentDate = !empty($params['date'])
                ? Carbon::parse($params['date'])
                : now();

        $paymentNumber = Payment::generatePaymentNumber($company->id);
        $currency = strtoupper($params['currency'] ?? $invoice->currency);
        if ($currency !== $invoice->currency && $currency !== $invoice->base_currency) {
            throw new \InvalidArgumentException('Payment currency must match invoice currency or base currency.');
        }
        $baseCurrency = $invoice->base_currency ?? $company->base_currency ?? $currency;
        $exchangeRate = $currency === $baseCurrency ? null : ($params['exchange_rate'] ?? $invoice->exchange_rate ?? null);
        if ($currency !== $baseCurrency && $exchangeRate === null) {
            throw new \InvalidArgumentException('exchange_rate is required when payment currency differs from base_currency.');
        }
        if ($currency === $baseCurrency) {
            $exchangeRate = null;
        }
        $baseAmount = round($amount * ($exchangeRate ?? 1), 2);

        // Create payment record
        $payment = Payment::create([
            'company_id' => $company->id,
            'customer_id' => $invoice->customer_id,
            'payment_number' => $paymentNumber,
            'payment_date' => $paymentDate,
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency' => $baseCurrency,
            'base_amount' => $baseAmount,
            'payment_method' => $params['method'] ?? 'bank_transfer',
            'reference_number' => $params['reference'] ?? null,
            'notes' => $params['notes'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        // Create payment allocation
        PaymentAllocation::create([
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_allocated' => $amount,
            'base_amount_allocated' => $baseAmount,
            'applied_at' => $paymentDate,
        ]);

        // Update invoice
        $newBalance = $invoice->balance - $amount;
        $newPaidAmount = $invoice->paid_amount + $amount;

        $newStatus = $newBalance <= 0
            ? 'paid'
            : ($newPaidAmount > 0 ? 'partial' : $invoice->status);

        $invoice->update([
            'status' => $newStatus,
            'paid_amount' => $newPaidAmount,
            'balance' => max(0, $newBalance),
            'paid_at' => $newStatus === 'paid' ? now() : null,
        ]);

            $statusMsg = $newStatus === 'paid'
                ? '{success}Paid in full{/}'
                : PaletteFormatter::money(max(0, $newBalance), $invoice->currency) . ' remaining';

            return [
                'message' => "Payment recorded: " .
                    PaletteFormatter::money($amount, $invoice->currency) .
                    " on {$invoice->invoice_number} — {$statusMsg}",
                'data' => [
                    'id' => $payment->id,
                    'invoice' => $invoice->invoice_number,
                    'amount' => PaletteFormatter::money($amount, $invoice->currency),
                    'balance' => PaletteFormatter::money(max(0, $newBalance), $invoice->currency),
                    'status' => $newStatus,
                ],
            ];
        });
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
