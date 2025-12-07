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

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
            'invoice' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'method' => 'required|string|in:cash,check,card,bank_transfer,other',
            'currency' => 'nullable|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|max:999999999',
            'date' => 'nullable|date|before_or_equal:today',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        // Get the payment
        $payment = Payment::where('id', $params['id'])
            ->where('company_id', $company->id)
            ->firstOrFail();

        // Resolve new invoice
        $invoice = $this->resolveInvoice($params['invoice'], $company->id);

        // Validate invoice status
        if ($invoice->status === 'cancelled') {
            throw new \Exception("Cannot apply payment to cancelled invoice");
        }

        if ($invoice->status === 'draft') {
            throw new \Exception("Cannot apply payment to draft invoice. Send it first.");
        }

        $newAmount = (float) $params['amount'];

        return DB::transaction(function () use ($params, $company, $payment, $invoice, $newAmount) {
            $paymentDate = !empty($params['date'])
                ? Carbon::parse($params['date'])
                : $payment->payment_date;

            $currency = strtoupper($params['currency'] ?? $payment->currency);
            $baseCurrency = $invoice->base_currency ?? $company->base_currency ?? $currency;
            $exchangeRate = $currency === $baseCurrency ? null : ($params['exchange_rate'] ?? $payment->exchange_rate ?? null);

            if ($currency !== $baseCurrency && $exchangeRate === null) {
                throw new \InvalidArgumentException('exchange_rate is required when currency differs from base_currency.');
            }

            if ($currency === $baseCurrency) {
                $exchangeRate = null;
            }

            $baseAmount = round($newAmount * ($exchangeRate ?? 1), 2);

            // Get original allocation to reverse
            $originalAllocation = $payment->paymentAllocations()->first();
            $originalInvoice = null;

            if ($originalAllocation) {
                $originalInvoice = $originalAllocation->invoice;

                // Reverse original allocation on old invoice
                $oldBalance = $originalInvoice->balance + $originalAllocation->amount_allocated;
                $oldPaidAmount = $originalInvoice->paid_amount - $originalAllocation->amount_allocated;

                $oldStatus = $oldBalance >= $originalInvoice->total_amount
                    ? 'sent'
                    : ($oldPaidAmount > 0 ? 'partial' : 'sent');

                $originalInvoice->update([
                    'status' => $oldStatus,
                    'paid_amount' => $oldPaidAmount,
                    'balance' => $oldBalance,
                    'paid_at' => null,
                ]);
            }

            // Check if we're applying to a different invoice or same invoice with different amount
            if (!$originalAllocation || $originalAllocation->invoice_id !== $invoice->id || $originalAllocation->amount_allocated !== $newAmount) {
                // Validate payment amount against new invoice balance
                $currentInvoiceBalance = $invoice->balance;
                if ($originalAllocation && $originalAllocation->invoice_id === $invoice->id) {
                    // If same invoice, we can reuse the original allocation amount
                    $currentInvoiceBalance += $originalAllocation->amount_allocated;
                }

                if ($newAmount > $currentInvoiceBalance) {
                    throw new \Exception(
                        "Payment amount ({$newAmount}) exceeds balance due " .
                        "(" . PaletteFormatter::money($currentInvoiceBalance, $invoice->currency) . "). " .
                        "Maximum payment: " . PaletteFormatter::money($currentInvoiceBalance, $invoice->currency)
                    );
                }
            }

            // Update payment record
            $payment->update([
                'customer_id' => $invoice->customer_id,
                'payment_date' => $paymentDate,
                'amount' => $newAmount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_amount' => $baseAmount,
                'payment_method' => $params['method'] ?? 'bank_transfer',
                'reference_number' => $params['reference'] ?? null,
                'notes' => $params['notes'] ?? null,
                'updated_by_user_id' => Auth::id(),
            ]);

            // Delete existing allocations
            PaymentAllocation::where('payment_id', $payment->id)->delete();

            // Create new payment allocation
            PaymentAllocation::create([
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount_allocated' => $newAmount,
                'base_amount_allocated' => $baseAmount,
                'applied_at' => $paymentDate,
            ]);

            // Update new invoice
            $newBalance = $invoice->balance - $newAmount;
            $newPaidAmount = $invoice->paid_amount + $newAmount;

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
                'message' => "Payment updated: " .
                    PaletteFormatter::money($newAmount, $invoice->currency) .
                    " on {$invoice->invoice_number} — {$statusMsg}",
                'data' => [
                    'id' => $payment->id,
                    'invoice' => $invoice->invoice_number,
                    'amount' => PaletteFormatter::money($newAmount, $invoice->currency),
                    'balance' => PaletteFormatter::money(max(0, $newBalance), $invoice->currency),
                    'status' => $newStatus,
                ],
                'redirect' => "/{$company->slug}/payments/{$payment->id}",
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