<?php

namespace Modules\Invoicing\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentService - Handles payment business logic
 *
 * This service follows the Haasib Constitution principles, particularly:
 * - RBAC Integrity: Respects seeded role/permission catalog
 * - Tenancy & RLS Safety: Enforces company scoping
 * - Audit, Idempotency & Observability: Logs all payment operations
 * - Module Governance: Part of the Invoicing module
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class PaymentService
{
    use AuditLogging;

    /**
     * Record a payment against an invoice
     *
     * @param  Company  $company  The company context
     * @param  Invoice  $invoice  The invoice to apply payment to
     * @param  float  $amount  The payment amount
     * @param  string  $method  The payment method
     * @param  string  $reference  The payment reference
     * @param  string|null  $notes  Additional notes for the payment
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $paymentDate  The date of payment (defaults to current date)
     * @return Payment The created payment
     *
     * @throws \Throwable If the payment recording fails
     */
    public function recordPayment(
        Company $company,
        Invoice $invoice,
        float $amount,
        string $method,
        string $reference,
        ?string $notes,
        ServiceContext $context,
        ?string $paymentDate = null
    ): Payment {
        if (! $invoice->canBePaid()) {
            throw new \InvalidArgumentException('Invoice cannot be paid in current status');
        }

        $idempotencyKey = $context->getIdempotencyKey();

        try {
            $result = DB::transaction(function () use (
                $company,
                $invoice,
                $amount,
                $method,
                $reference,
                $notes,
                $paymentDate,
                $idempotencyKey
            ) {
                // Validate the payment amount
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Payment amount must be greater than zero');
                }

                // Check that payment doesn't exceed the balance due
                if ($amount > $invoice->balance_due) {
                    throw new \InvalidArgumentException(
                        'Payment amount exceeds invoice balance due: '.$invoice->balance_due
                    );
                }

                // Create payment record
                $payment = new Payment([
                    'company_id' => $company->id,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'notes' => $notes,
                    'payment_date' => $paymentDate ?? now()->toDateString(),
                    'idempotency_key' => $idempotencyKey,
                ]);

                if (! $payment->save()) {
                    throw new \RuntimeException('Failed to save payment: validation failed');
                }

                // Update invoice balance and status
                $invoice->paid_amount += $amount;
                $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

                // Update invoice status based on payment status
                if ($invoice->balance_due <= 0.001) { // Small tolerance for floating point errors
                    $invoice->status = 'paid';
                } elseif ($invoice->paid_amount > 0) {
                    $invoice->status = 'partial';
                }

                $invoice->save();

                return $payment->fresh();
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in recordPayment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);
            throw $e;
        }

        if (! $result) {
            throw new \RuntimeException('DB transaction returned null');
        }

        $this->logAudit('payment.recorded', [
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $result->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
        ], $context);

        return $result;
    }

    /**
     * Record a payment against multiple invoices
     *
     * @param  Company  $company  The company context
     * @param  Customer  $customer  The customer making payment
     * @param  array  $invoicePayments  Array of ['invoice_id' => id, 'amount' => amount] pairs
     * @param  string  $method  The payment method
     * @param  string  $reference  The payment reference
     * @param  string|null  $notes  Additional notes for the payment
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $paymentDate  The date of payment (defaults to current date)
     * @return array Array of created payments and results
     *
     * @throws \Throwable If the payment recording fails
     */
    public function recordPaymentForMultipleInvoices(
        Company $company,
        Customer $customer,
        array $invoicePayments,
        string $method,
        string $reference,
        ?string $notes,
        ServiceContext $context,
        ?string $paymentDate = null
    ): array {
        $idempotencyKey = $context->getIdempotencyKey();
        $results = [];

        try {
            $result = DB::transaction(function () use (
                $company,
                $customer,
                $invoicePayments,
                $method,
                $reference,
                $notes,
                $paymentDate,
                $idempotencyKey,
                &$results
            ) {
                $totalPayment = 0;

                // Validate all invoices first
                foreach ($invoicePayments as $idx => $paymentInfo) {
                    $invoiceId = $paymentInfo['invoice_id'];
                    $amount = $paymentInfo['amount'];

                    $invoice = Invoice::find($invoiceId);

                    if (! $invoice) {
                        throw new \InvalidArgumentException("Invoice with ID {$invoiceId} not found");
                    }

                    if ($invoice->company_id !== $company->id) {
                        throw new \InvalidArgumentException("Invoice {$invoiceId} does not belong to company {$company->id}");
                    }

                    if (! $invoice->canBePaid()) {
                        throw new \InvalidArgumentException("Invoice {$invoiceId} cannot be paid in current status");
                    }

                    if ($amount <= 0) {
                        throw new \InvalidArgumentException("Payment amount must be greater than zero for invoice {$invoiceId}");
                    }

                    if ($amount > $invoice->balance_due) {
                        throw new \InvalidArgumentException(
                            "Payment amount for invoice {$invoiceId} exceeds balance due: {$invoice->balance_due}"
                        );
                    }

                    $totalPayment += $amount;
                }

                // Create payment records and update invoices
                foreach ($invoicePayments as $idx => $paymentInfo) {
                    $invoiceId = $paymentInfo['invoice_id'];
                    $amount = $paymentInfo['amount'];

                    $invoice = Invoice::find($invoiceId);

                    // Create payment record
                    $payment = new Payment([
                        'company_id' => $company->id,
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'method' => $method,
                        'reference' => $reference."-{$idx}", // Add index to reference to make it unique
                        'notes' => $notes,
                        'payment_date' => $paymentDate ?? now()->toDateString(),
                        'idempotency_key' => $idempotencyKey."-{$idx}",
                    ]);

                    if (! $payment->save()) {
                        throw new \RuntimeException("Failed to save payment for invoice {$invoiceId}: validation failed");
                    }

                    // Update invoice balance and status
                    $invoice->paid_amount += $amount;
                    $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

                    // Update invoice status based on payment status
                    if ($invoice->balance_due <= 0.001) { // Small tolerance for floating point errors
                        $invoice->status = 'paid';
                    } elseif ($invoice->paid_amount > 0) {
                        $invoice->status = 'partial';
                    }

                    $invoice->save();

                    $results[] = [
                        'payment' => $payment->fresh(),
                        'invoice' => $invoice->fresh(),
                    ];
                }

                return $results;
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in recordPaymentForMultipleInvoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_payments' => $invoicePayments,
            ]);
            throw $e;
        }

        // Log each payment that was recorded
        foreach ($results as $result) {
            $payment = $result['payment'];
            $this->logAudit('payment.recorded', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $method,
                'reference' => $payment->reference,
            ], $context);
        }

        return $results;
    }

    /**
     * Refund a payment
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float  $amount  The refund amount (must be <= payment amount)
     * @param  string|null  $reason  The reason for refund
     * @param  ServiceContext  $context  The service context
     * @return Payment The updated payment with refund information
     *
     * @throws \InvalidArgumentException If refund is not allowed
     * @throws \Throwable If the refund fails
     */
    public function refundPayment(
        Payment $payment,
        float $amount,
        ?string $reason,
        ServiceContext $context
    ): Payment {
        // Check if payment has already been refunded completely
        if ($payment->refunded_amount >= $payment->amount) {
            throw new \InvalidArgumentException('Payment has already been fully refunded');
        }

        // Check if refund amount is valid
        $remainingAmount = $payment->amount - $payment->refunded_amount;
        if ($amount > $remainingAmount) {
            throw new \InvalidArgumentException(
                "Refund amount exceeds remaining payment amount: {$remainingAmount}"
            );
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero');
        }

        $result = DB::transaction(function () use ($payment, $amount, $reason) {
            // Update the payment with refund information
            $payment->refunded_amount += $amount;
            $payment->refunded_at = now();
            $payment->refunded_reason = $reason;

            if ($payment->refunded_amount >= $payment->amount - 0.001) { // Account for floating point errors
                $payment->status = 'refunded';
            } else {
                $payment->status = 'partially_refunded';
            }

            $payment->save();

            // Update the related invoice to reduce paid amount
            $invoice = $payment->invoice;
            $invoice->paid_amount -= $amount;
            $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

            // Update invoice status based on payment status
            if ($invoice->balance_due <= 0.001) {
                $invoice->status = 'paid';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partial';
            } else {
                $invoice->status = 'unpaid';
            }

            $invoice->save();

            return $payment->fresh();
        });

        $this->logAudit('payment.refunded', [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'amount' => $amount,
            'reason' => $reason,
            'remaining_refundable' => $payment->amount - $payment->refunded_amount,
        ], $context);

        return $result;
    }

    /**
     * Get payments for a company with pagination
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  int  $perPage  Number of results per page
     * @param  string|null  $status  Optional status filter
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @return LengthAwarePaginator The payments
     */
    public function getPaymentsForCompany(
        Company $company,
        ServiceContext $context,
        int $perPage = 20,
        ?string $status = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): LengthAwarePaginator {
        $query = Payment::where('company_id', $company->id);

        if ($status) {
            $query->where('status', $status);
        }

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        $payments = $query->with(['invoice', 'customer'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $this->logAudit('payment.list_viewed', [
            'company_id' => $company->id,
            'status_filter' => $status,
            'total_count' => $payments->total(),
        ], $context);

        return $payments;
    }

    /**
     * Get payments for a specific invoice
     *
     * @param  Invoice  $invoice  The invoice to get payments for
     * @param  ServiceContext  $context  The service context
     * @return \Illuminate\Database\Eloquent\Collection The payments
     */
    public function getPaymentsForInvoice(Invoice $invoice, ServiceContext $context)
    {
        $payments = Payment::where('invoice_id', $invoice->id)
            ->get();

        $this->logAudit('invoice.payments_viewed', [
            'invoice_id' => $invoice->id,
            'payment_count' => $payments->count(),
        ], $context);

        return $payments;
    }

    /**
     * Allocate a payment across multiple invoices
     *
     * @param  Company  $company  The company context
     * @param  Customer  $customer  The customer making payment
     * @param  float  $amount  The total payment amount
     * @param  array  $invoiceIds  Array of invoice IDs to allocate against
     * @param  string  $method  The payment method
     * @param  string  $reference  The payment reference
     * @param  string|null  $notes  Additional notes for the payment
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $paymentDate  The date of payment (defaults to current date)
     * @return array Array of created payments
     *
     * @throws \InvalidArgumentException If allocation is not possible
     * @throws \Throwable If the allocation fails
     */
    public function allocatePaymentToInvoices(
        Company $company,
        Customer $customer,
        float $amount,
        array $invoiceIds,
        string $method,
        string $reference,
        ?string $notes,
        ServiceContext $context,
        ?string $paymentDate = null
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero');
        }

        if (empty($invoiceIds)) {
            throw new \InvalidArgumentException('At least one invoice ID must be provided');
        }

        $idempotencyKey = $context->getIdempotencyKey();
        $payments = [];

        try {
            $results = DB::transaction(function () use (
                $company,
                $customer,
                $amount,
                $invoiceIds,
                $method,
                $reference,
                $notes,
                $paymentDate,
                $idempotencyKey,
                &$payments
            ) {
                // Get the invoices and their balances
                $invoices = Invoice::whereIn('id', $invoiceIds)
                    ->where('company_id', $company->id)
                    ->where('customer_id', $customer->id)
                    ->get();

                if ($invoices->count() !== count($invoiceIds)) {
                    throw new \InvalidArgumentException('One or more invoices not found or do not belong to the specified company/customer');
                }

                // Check all invoices can be paid
                foreach ($invoices as $invoice) {
                    if (! $invoice->canBePaid()) {
                        throw new \InvalidArgumentException("Invoice {$invoice->id} cannot be paid in current status");
                    }
                }

                // Sort invoices by date (oldest first) for proper allocation
                $invoices = $invoices->sortBy('invoice_date')->values();

                // Allocate the payment amount across the invoices
                $remainingAmount = $amount;
                $allocationResults = [];

                foreach ($invoices as $invoice) {
                    if ($remainingAmount <= 0) {
                        break; // Payment fully allocated
                    }

                    $amountToApply = min($remainingAmount, $invoice->balance_due);

                    if ($amountToApply <= 0) {
                        continue; // Skip if no balance to pay
                    }

                    // Create payment record
                    $payment = new Payment([
                        'company_id' => $company->id,
                        'customer_id' => $customer->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amountToApply,
                        'method' => $method,
                        'reference' => $reference.'-alloc-'.$invoice->id,
                        'notes' => $notes,
                        'payment_date' => $paymentDate ?? now()->toDateString(),
                        'idempotency_key' => $idempotencyKey.'-alloc-'.$invoice->id,
                    ]);

                    if (! $payment->save()) {
                        throw new \RuntimeException("Failed to save payment for invoice {$invoice->id}: validation failed");
                    }

                    // Update invoice balance and status
                    $invoice->paid_amount += $amountToApply;
                    $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

                    // Update invoice status based on payment status
                    if ($invoice->balance_due <= 0.001) { // Small tolerance for floating point errors
                        $invoice->status = 'paid';
                    } elseif ($invoice->paid_amount > 0) {
                        $invoice->status = 'partial';
                    }

                    $invoice->save();

                    $allocationResults[] = [
                        'payment' => $payment->fresh(),
                        'invoice' => $invoice->fresh(),
                        'amount_applied' => $amountToApply,
                    ];

                    $remainingAmount -= $amountToApply;
                }

                // If there's still remaining amount, the payment exceeds invoice balances
                if ($remainingAmount > 0.001) { // Tolerance for floating point errors
                    throw new \InvalidArgumentException(
                        'Payment amount exceeds the total balance of the selected invoices: '.$amount
                    );
                }

                return $allocationResults;
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in allocatePaymentToInvoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'invoice_ids' => $invoiceIds,
            ]);
            throw $e;
        }

        // Log each payment that was recorded
        foreach ($results as $result) {
            $payment = $result['payment'];
            $this->logAudit('payment.allocated', [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $method,
                'reference' => $payment->reference,
            ], $context);
        }

        return $results;
    }

    /**
     * Reverse a payment (cancel and refund)
     *
     * @param  Payment  $payment  The payment to reverse
     * @param  string|null  $reason  The reason for reversal
     * @param  ServiceContext  $context  The service context
     * @return Payment The updated payment
     *
     * @throws \InvalidArgumentException If payment cannot be reversed
     * @throws \Throwable If the reversal fails
     */
    public function reversePayment(Payment $payment, ?string $reason, ServiceContext $context): Payment
    {
        if ($payment->status === 'reversed') {
            throw new \InvalidArgumentException('Payment has already been reversed');
        }

        if ($payment->status === 'refunded') {
            throw new \InvalidArgumentException('Cannot reverse a refunded payment, use proper refund process');
        }

        $result = DB::transaction(function () use ($payment, $reason) {
            // Update the payment status
            $payment->status = 'reversed';
            $payment->reversed_at = now();
            $payment->reversed_reason = $reason;
            $payment->refunded_amount = $payment->amount; // Mark the full amount as refunded
            $payment->refunded_at = now();
            $payment->refunded_reason = $reason.' (reversed)';

            $payment->save();

            // Update the related invoice to reduce paid amount
            $invoice = $payment->invoice;
            $invoice->paid_amount -= $payment->amount;
            $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

            // Update invoice status based on payment status
            if ($invoice->balance_due <= 0.001) {
                $invoice->status = 'paid';
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partial';
            } else {
                $invoice->status = 'unpaid';
            }

            $invoice->save();

            return $payment->fresh();
        });

        $this->logAudit('payment.reversed', [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'amount' => $payment->amount,
            'reason' => $reason,
        ], $context);

        return $result;
    }

    /**
     * Get payment statistics
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @return array Statistics about payments
     */
    public function getPaymentStatistics(Company $company, ServiceContext $context, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::where('company_id', $company->id);

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        $payments = $query->get();

        $stats = [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'total_refunded' => $payments->sum('refunded_amount'),
            'net_amount' => $payments->sum('amount') - $payments->sum('refunded_amount'),
            'status_breakdown' => [
                'completed' => $payments->where('status', 'completed')->count(),
                'refunded' => $payments->where('status', 'refunded')->count(),
                'partially_refunded' => $payments->where('status', 'partially_refunded')->count(),
                'reversed' => $payments->where('status', 'reversed')->count(),
            ],
            'method_breakdown' => $payments->groupBy('method')->map->count(),
        ];

        $this->logAudit('payment.statistics_viewed', [
            'company_id' => $company->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $context);

        return $stats;
    }
}
