<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    use AuditLogging;

    /**
     * Create a new payment record
     *
     * @param  Company  $company  The company to create the payment for
     * @param  Customer  $customer  The customer making the payment
     * @param  Money  $amount  The payment amount
     * @param  Currency|null  $currency  The payment currency (defaults to customer/company currency)
     * @param  string  $paymentMethod  The payment method (cash, bank_transfer, etc.)
     * @param  string|null  $paymentDate  The payment date (defaults to current date)
     * @param  string|null  $paymentNumber  The payment number (auto-generated if null)
     * @param  string|null  $paymentReference  External payment reference
     * @param  string|null  $notes  Additional notes
     * @param  bool|null  $autoAllocate  Whether to auto-allocate to invoices
     * @param  array|null  $invoiceAllocations  Specific invoice allocations if autoAllocate is true
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Payment The created payment record
     *
     * @throws \Throwable If the payment creation fails
     */
    public function createPayment(
        Company $company,
        Customer $customer,
        Money $amount,
        ?Currency $currency,
        string $paymentMethod,
        ?string $paymentDate,
        ?string $paymentNumber,
        ?string $paymentReference,
        ?string $notes,
        ?bool $autoAllocate,
        ?array $invoiceAllocations,
        ServiceContext $context
    ): Payment {
        $idempotencyKey = $context->getIdempotencyKey();
        $result = DB::transaction(function () use ($company, $customer, $paymentMethod, $amount, $currency, $paymentDate, $paymentNumber, $paymentReference, $autoAllocate, $invoiceAllocations) {
            $currency = $currency ?? $customer->currency ?? $company->getDefaultCurrency();
            $exchangeRate = $this->getExchangeRate($currency, $company);

            $payment = new Payment([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'currency_id' => $currency->id,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_number' => $paymentNumber ?? $this->generateNextPaymentNumber($company->id),
                'amount' => $amount->getAmount(),
                'exchange_rate' => $exchangeRate,
                'payment_date' => $paymentDate ?? now()->toDateString(),
                'status' => 'pending',
            ]);

            $payment->save();

            // Handle auto-allocation if requested
            if ($autoAllocate && $invoiceAllocations) {
                // Pre-load all invoices with locks to prevent race conditions
                $invoiceIds = array_keys($invoiceAllocations);
                $invoices = Invoice::whereIn('invoice_id', $invoiceIds)
                    ->where('company_id', $company->id)
                    ->where('customer_id', $customer->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('invoice_id');

                foreach ($invoiceAllocations as $invoiceId => $allocationAmount) {
                    if ($allocationAmount > 0 && isset($invoices[$invoiceId])) {
                        $payment->allocateToInvoice($invoices[$invoiceId], Money::of($allocationAmount, $currency->code));
                    }
                }
            }

            return $payment->fresh(['customer', 'currency']);
        });

        $this->logAudit('payment.create', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'payment_method' => $paymentMethod,
            'amount' => $amount->getAmount(),
            'currency_id' => $currency->id,
            'payment_reference' => $result->payment_reference,
        ], $context, result: ['payment_id' => $result->id]);

        return $result;
    }

    /**
     * Process a pending payment and mark it as completed
     *
     * @param  Payment  $payment  The payment to process
     * @param  string|null  $processorReference  External processor reference
     * @param  array|null  $metadata  Additional metadata
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Payment The processed payment
     *
     * @throws \Throwable If the payment processing fails
     */
    public function processPayment(
        Payment $payment,
        ?string $processorReference,
        ?array $metadata,
        ServiceContext $context
    ): Payment {
        $result = DB::transaction(function () use ($payment, $processorReference, $context) {
            $payment->markAsCompleted($processorReference);

            // Auto-post completed payment to ledger
            try {
                app(\App\Services\LedgerIntegrationService::class)->postPaymentToLedger($payment, $context);
            } catch (\Throwable $e) {
                Log::error('Auto-post payment to ledger failed (non-fatal)', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $payment->fresh();
        });

        $this->logAudit('payment.process', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'processor_reference' => $processorReference,
        ], $context, result: ['processed_at' => now()->toISOString()]);

        return $result;
    }

    /**
     * Convenience overload: create + complete + optional auto-allocate + auto-post.
     */
    /**
     * Convenience method: create + complete + optional auto-allocate + auto-post
     *
     * @param  Company  $company  The company to create the payment for
     * @param  Customer  $customer  The customer making the payment
     * @param  float  $amount  The payment amount
     * @param  string  $paymentMethod  The payment method
     * @param  string|null  $paymentReference  External payment reference
     * @param  string|null  $paymentDate  The payment date
     * @param  Currency|null  $currency  The payment currency
     * @param  float|null  $exchangeRate  The exchange rate (if different from default)
     * @param  string|null  $notes  Additional notes
     * @param  bool|null  $autoAllocate  Whether to auto-allocate to invoices
     * @param  string|null  $idempotencyKey  Unique key for idempotency
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Payment The created and processed payment
     *
     * @throws \Throwable If the payment creation or processing fails
     */
    public function processIncomingPayment(
        Company $company,
        Customer $customer,
        float $amount,
        string $paymentMethod,
        ?string $paymentReference,
        ?string $paymentDate,
        ?Currency $currency,
        ?float $exchangeRate,
        ?string $notes,
        ?bool $autoAllocate,
        ?string $idempotencyKey,
        ServiceContext $context
    ): Payment {
        $money = Money::of($amount, ($currency?->code) ?? ($customer->currency?->code) ?? ($company->base_currency ?? 'USD'));
        $payment = $this->createPayment(
            company: $company,
            customer: $customer,
            amount: $money,
            currency: $currency,
            paymentMethod: $paymentMethod,
            paymentDate: $paymentDate,
            paymentNumber: null,
            paymentReference: $paymentReference,
            notes: $notes,
            autoAllocate: false,
            invoiceAllocations: null,
            context: $context
        );

        $payment = $this->processPayment($payment, null, null, $context);

        if ($autoAllocate) {
            try {
                $this->autoAllocatePayment($payment, $context);
            } catch (\Throwable $e) {
                Log::warning('Auto-allocate after process failed (non-fatal)', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment->fresh(['allocations.invoice']);
    }

    /**
     * Allocate payment to specific invoices
     *
     * @param  Payment  $payment  The payment to allocate
     * @param  array  $allocations  Array of allocation data with invoice_id and amount
     * @param  string|null  $notes  Additional notes for allocations
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return array Array of created payment allocations
     *
     * @throws \InvalidArgumentException If the payment cannot be allocated or allocations are invalid
     * @throws \Throwable If the allocation fails
     */
    public function allocatePayment(
        Payment $payment,
        array $allocations,
        ?string $notes,
        ServiceContext $context
    ): array {
        $result = DB::transaction(function () use ($payment, $allocations, $notes) {
            if (! $payment->canBeAllocated()) {
                throw new \InvalidArgumentException('Payment cannot be allocated');
            }

            $createdAllocations = [];
            $totalAllocationAmount = Money::of(0, $payment->currency->code);

            // Pre-load all invoices with locks to prevent race conditions
            $invoiceIds = array_column($allocations, 'invoice_id');
            $invoices = Invoice::whereIn('invoice_id', $invoiceIds)
                ->where('company_id', $payment->company_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('invoice_id');

            foreach ($allocations as $allocation) {
                $invoice = $invoices[$allocation['invoice_id']] ?? null;
                if (! $invoice) {
                    throw new \InvalidArgumentException("Invalid invoice ID: {$allocation['invoice_id']}");
                }

                $allocationAmount = Money::of($allocation['amount'], $payment->currency->code);
                $totalAllocationAmount = $totalAllocationAmount->plus($allocationAmount);

                if ($totalAllocationAmount->isGreaterThan($payment->getUnallocatedAmount())) {
                    throw new \InvalidArgumentException('Total allocation amount exceeds unallocated payment amount');
                }

                $paymentAllocation = $payment->allocateToInvoice(
                    $invoice,
                    $allocationAmount,
                    $allocation['notes'] ?? $notes
                );

                $createdAllocations[] = $paymentAllocation;
            }

            return $createdAllocations;
        });

        $this->logAudit('payment.allocate', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'allocations_count' => count($result),
            'total_allocated' => array_sum(array_column($result, 'amount')),
        ], $context, result: ['allocation_ids' => array_column($result, 'id')]);

        return $result;
    }

    /**
     * Automatically allocate payment to outstanding invoices
     *
     * @param  Payment  $payment  The payment to auto-allocate
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return array Array of created payment allocations
     *
     * @throws \Throwable If the auto-allocation fails
     */
    public function autoAllocatePayment(Payment $payment, ServiceContext $context): array
    {
        $result = DB::transaction(function () use ($payment) {
            return $payment->autoAllocate();
        });

        $this->logAudit('payment.auto_allocate', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'allocations_created' => count($result),
            'total_allocated' => array_sum(array_column($result, 'amount')),
        ], $context, result: ['allocation_ids' => array_column($result, 'id')]);

        return $result;
    }

    /**
     * Void a payment
     *
     * @param  Payment  $payment  The payment to void
     * @param  string|null  $reason  The reason for voiding
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Payment The voided payment
     *
     * @throws \InvalidArgumentException If the payment cannot be voided
     * @throws \Throwable If the void operation fails
     */
    public function voidPayment(Payment $payment, ?string $reason, ServiceContext $context): Payment
    {
        $result = DB::transaction(function () use ($payment, $reason) {
            $payment->markAsCancelled($reason);

            return $payment->fresh();
        });

        $this->logAudit('payment.void', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'reason' => $reason,
        ], $context, result: ['voided_at' => $result->metadata['cancelled_at']]);

        return $result;
    }

    /**
     * Refund a payment
     *
     * @param  Payment  $payment  The payment to refund
     * @param  Money  $amount  The refund amount
     * @param  string|null  $reason  The reason for refund
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return array Array of created refunds
     *
     * @throws \InvalidArgumentException If the payment cannot be refunded
     * @throws \Throwable If the refund operation fails
     */
    public function refundPayment(
        Payment $payment,
        Money $amount,
        ?string $reason,
        ServiceContext $context
    ): array {
        $result = DB::transaction(function () use ($payment, $amount, $reason) {
            return $payment->refund($amount, $reason);
        });

        $this->logAudit('payment.refund', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'refund_amount' => $amount->getAmount(),
            'reason' => $reason,
        ], $context, result: ['refund_ids' => array_column($result, 'id')]);

        return $result;
    }

    /**
     * Void a payment allocation
     *
     * @param  PaymentAllocation  $allocation  The allocation to void
     * @param  string|null  $reason  The reason for voiding
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return PaymentAllocation The voided allocation
     *
     * @throws \InvalidArgumentException If the allocation cannot be voided
     * @throws \Throwable If the void operation fails
     */
    public function voidAllocation(PaymentAllocation $allocation, ?string $reason, ServiceContext $context): PaymentAllocation
    {
        $result = DB::transaction(function () use ($allocation, $reason) {
            $allocation->void($reason);

            return $allocation->fresh();
        });

        $this->logAudit('payment.allocation.void', [
            'allocation_id' => $allocation->id,
            'payment_id' => $allocation->payment_id,
            'invoice_id' => $allocation->invoice_id,
            'amount' => $allocation->amount,
            'reason' => $reason,
        ], $context, result: ['voided_at' => $result->metadata['voided_at']]);

        return $result;
    }

    /**
     * Refund a payment allocation
     *
     * @param  PaymentAllocation  $allocation  The allocation to refund
     * @param  Money  $amount  The refund amount
     * @param  string|null  $reason  The reason for refund
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return PaymentAllocation The refunded allocation
     *
     * @throws \InvalidArgumentException If the allocation cannot be refunded
     * @throws \Throwable If the refund operation fails
     */
    public function refundAllocation(
        PaymentAllocation $allocation,
        Money $amount,
        ?string $reason,
        ServiceContext $context
    ): PaymentAllocation {
        $result = DB::transaction(function () use ($allocation, $amount, $reason) {
            return $allocation->refund($amount, $reason);
        });

        $this->logAudit('payment.allocation.refund', [
            'allocation_id' => $allocation->id,
            'payment_id' => $allocation->payment_id,
            'invoice_id' => $allocation->invoice_id,
            'refund_amount' => $amount->getAmount(),
            'reason' => $reason,
        ], $context, result: ['refund_id' => $result->id]);

        return $result;
    }

    public function getCustomerPaymentSummary(Customer $customer, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id);

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'total_allocated' => $payments->sum(fn ($p) => $p->getAllocatedAmount()->getAmount()),
            'total_unallocated' => $payments->sum(fn ($p) => $p->getUnallocatedAmount()->getAmount()),
            'status_breakdown' => [
                'pending' => $payments->where('status', 'pending')->count(),
                'completed' => $payments->where('status', 'completed')->count(),
                'failed' => $payments->where('status', 'failed')->count(),
                'cancelled' => $payments->where('cancelled')->count(),
            ],
            'payment_method_breakdown' => $payments->groupBy('payment_method')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ])
                ->toArray(),
        ];
    }

    public function getCompanyPaymentStatistics(Company $company, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::where('company_id', $company->id);

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'total_allocated' => $payments->sum(fn ($p) => $p->getAllocatedAmount()->getAmount()),
            'total_unallocated' => $payments->sum(fn ($p) => $p->getUnallocatedAmount()->getAmount()),
            'average_payment_amount' => $payments->avg('amount'),
            'status_breakdown' => [
                'pending' => $payments->where('status', 'pending')->count(),
                'completed' => $payments->where('status', 'completed')->count(),
                'failed' => $payments->where('status', 'failed')->count(),
                'cancelled' => $payments->where('cancelled')->count(),
            ],
            'payment_method_breakdown' => $payments->groupBy('payment_method')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ])
                ->toArray(),
            'currency_breakdown' => $payments->groupBy('currency.code')
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ])
                ->toArray(),
        ];
    }

    public function getPaymentAgingReport(Company $company, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $outstandingInvoices = Invoice::where('company_id', $company->id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->get();

        $agingCategories = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
        ];

        foreach ($outstandingInvoices as $invoice) {
            $daysOverdue = max(0, now()->diffInDays($invoice->due_date));

            if ($daysOverdue <= 0) {
                $agingCategories['current'] += $invoice->balance_due;
            } elseif ($daysOverdue <= 30) {
                $agingCategories['1_30_days'] += $invoice->balance_due;
            } elseif ($daysOverdue <= 60) {
                $agingCategories['31_60_days'] += $invoice->balance_due;
            } elseif ($daysOverdue <= 90) {
                $agingCategories['61_90_days'] += $invoice->balance_due;
            } else {
                $agingCategories['over_90_days'] += $invoice->balance_due;
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'total_outstanding' => array_sum($agingCategories),
            'aging_breakdown' => $agingCategories,
            'invoice_count' => $outstandingInvoices->count(),
        ];
    }

    public function bulkProcessPayments(array $paymentIds): array
    {
        $results = [];

        foreach ($paymentIds as $paymentId) {
            try {
                $payment = Payment::findOrFail($paymentId);

                if (! $payment->canBeAllocated()) {
                    throw new \InvalidArgumentException('Payment cannot be processed');
                }

                $payment = $this->processPayment($payment);
                $allocations = $this->autoAllocatePayment($payment);

                $results[] = [
                    'payment_id' => $paymentId,
                    'success' => true,
                    'new_status' => $payment->status,
                    'allocations_created' => count($allocations),
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'payment_id' => $paymentId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function getExchangeRate(Currency $currency, Company $company): float
    {
        if ($currency->code === $company->base_currency) {
            return 1.0;
        }

        $exchangeRate = \App\Models\ExchangeRate::where('from_currency', $currency->code)
            ->where('to_currency', $company->base_currency)
            ->latest()
            ->first();

        return $exchangeRate?->rate ?? 1.0;
    }

    public function validatePaymentData(array $data): void
    {
        if (! isset($data['amount']) || $data['amount'] <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive');
        }

        if (! isset($data['payment_method']) || empty(trim($data['payment_method']))) {
            throw new \InvalidArgumentException('Payment method is required');
        }

        $validMethods = ['cash', 'check', 'bank_transfer', 'credit_card', 'debit_card', 'paypal', 'stripe', 'other'];
        if (! in_array($data['payment_method'], $validMethods)) {
            throw new \InvalidArgumentException('Invalid payment method');
        }

        if (isset($data['payment_date']) && strtotime($data['payment_date']) === false) {
            throw new \InvalidArgumentException('Invalid payment date format');
        }
    }

    public function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'check' => 'Check',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'other' => 'Other',
        ];
    }

    public function generateNextPaymentNumber(string $companyId): string
    {
        $payment = new Payment;
        $payment->company_id = $companyId;

        return $payment->generatePaymentNumber();
    }

    /**
     * Update an existing payment
     *
     * @param  Payment  $payment  The payment to update
     * @param  Customer  $customer  The customer making the payment
     * @param  float  $amount  The payment amount
     * @param  int  $currencyId  The currency ID
     * @param  string  $paymentMethod  The payment method
     * @param  string  $paymentDate  The payment date
     * @param  string  $paymentNumber  The payment number
     * @param  string|null  $referenceNumber  External reference number
     * @param  string|null  $notes  Additional notes
     * @param  bool|null  $autoAllocate  Whether to auto-allocate to invoices
     * @param  array|null  $invoiceAllocations  Specific invoice allocations
     * @param  string|null  $idempotencyKey  Unique key for idempotency
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return Payment The updated payment
     *
     * @throws \InvalidArgumentException If the payment cannot be updated or data is invalid
     * @throws \Throwable If the update operation fails
     */
    public function updatePayment(
        Payment $payment,
        Customer $customer,
        float $amount,
        int $currencyId,
        string $paymentMethod,
        string $paymentDate,
        string $paymentNumber,
        ?string $referenceNumber,
        ?string $notes,
        ?bool $autoAllocate,
        ?array $invoiceAllocations,
        ?string $idempotencyKey,
        ServiceContext $context
    ): Payment {
        $result = DB::transaction(function () use ($payment, $customer, $amount, $currencyId, $paymentMethod, $paymentDate, $paymentNumber, $referenceNumber, $notes) {
            // Only allow updates to pending payments
            if ($payment->status !== 'pending') {
                throw new \InvalidArgumentException('Only pending payments can be updated');
            }

            $currency = Currency::findOrFail($currencyId);
            $exchangeRate = $this->getExchangeRate($currency, $payment->company);

            $payment->update([
                'customer_id' => $customer->id,
                'currency_id' => $currency->id,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'payment_number' => $paymentNumber,
                'payment_reference' => $referenceNumber,
                'notes' => $notes,
                'amount' => $amount,
                'exchange_rate' => $exchangeRate,
            ]);

            return $payment->fresh(['customer', 'currency']);
        });

        $this->logAudit('payment.update', [
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'currency_id' => $currencyId,
        ], $context, result: ['payment_id' => $result->id]);

        return $result;
    }

    /**
     * Delete a payment
     *
     * @param  Payment  $payment  The payment to delete
     * @param  ServiceContext  $context  The service context containing user and company information
     * @return bool True if the payment was successfully deleted
     *
     * @throws \InvalidArgumentException If the payment cannot be deleted
     * @throws \Throwable If the delete operation fails
     */
    public function deletePayment(Payment $payment, ServiceContext $context): bool
    {
        $result = DB::transaction(function () use ($payment) {
            // Only allow deletion of pending payments
            if ($payment->status !== 'pending') {
                throw new \InvalidArgumentException('Only pending payments can be deleted');
            }

            $paymentId = $payment->id;
            $payment->delete();

            return true;
        });

        $this->logAudit('payment.delete', [
            'payment_id' => $payment->id,
            'company_id' => $payment->company_id,
            'customer_id' => $payment->customer_id,
            'amount' => $payment->amount,
        ], $context);

        return $result;
    }
}
