<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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
            'method' => 'nullable|string|in:cash,check,card,bank_transfer,other',
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
        if ($amount > $invoice->balance_due) {
            throw new \Exception(
                "Payment amount ({$amount}) exceeds balance due " .
                "(" . PaletteFormatter::money($invoice->balance_due, $invoice->currency) . "). " .
                "Maximum payment: " . PaletteFormatter::money($invoice->balance_due, $invoice->currency)
            );
        }

        return DB::transaction(function () use ($params, $company, $invoice, $amount) {
            $paymentDate = !empty($params['date'])
                ? Carbon::parse($params['date'])
                : now();

            // Create payment record
            $payment = Payment::create([
                'company_id' => $company->id,
                'paymentable_type' => Invoice::class,
                'paymentable_id' => $invoice->id,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'method' => $params['method'] ?? 'bank_transfer',
                'reference' => $params['reference'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => $params['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            // Create payment allocation
            PaymentAllocation::create([
                'company_id' => $company->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'allocated_at' => $paymentDate,
            ]);

            // Update invoice
            $newAmountPaid = ($invoice->total_amount - $invoice->balance_due) + $amount;
            $newBalanceDue = $invoice->total_amount - $newAmountPaid;

            $newStatus = $newBalanceDue <= 0
                ? 'paid'
                : ($newBalanceDue < $invoice->total_amount ? 'posted' : 'sent');

            $newPaymentStatus = $newBalanceDue <= 0
                ? 'paid'
                : 'unpaid';

            $invoice->update([
                'payment_status' => $newPaymentStatus,
                'balance_due' => max(0, $newBalanceDue),
                'paid_at' => $newPaymentStatus === 'paid' ? now() : null,
            ]);

            $statusMsg = $newPaymentStatus === 'paid'
                ? '{success}Paid in full{/}'
                : PaletteFormatter::money($newBalanceDue, $invoice->currency) . ' remaining';

            return [
                'message' => "Payment recorded: " .
                    PaletteFormatter::money($amount, $invoice->currency) .
                    " on {$invoice->invoice_number} — {$statusMsg}",
                'data' => [
                    'id' => $payment->id,
                    'invoice' => $invoice->invoice_number,
                    'amount' => PaletteFormatter::money($amount, $invoice->currency),
                    'balance_due' => PaletteFormatter::money(max(0, $newBalanceDue), $invoice->currency),
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