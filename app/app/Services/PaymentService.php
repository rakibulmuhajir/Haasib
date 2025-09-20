<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createPayment(
        Company $company,
        Customer $customer,
        Money $amount,
        ?Currency $currency = null,
        string $paymentMethod = '',
        ?string $paymentDate = null,
        ?string $paymentNumber = null,
        ?string $paymentReference = null,
        ?string $notes = null,
        ?bool $autoAllocate = false,
        ?array $invoiceAllocations = null,
        ?string $idempotencyKey = null
    ): Payment {
        $result = DB::transaction(function () use ($company, $customer, $paymentMethod, $amount, $currency, $paymentDate, $paymentNumber, $paymentReference, $notes, $autoAllocate, $invoiceAllocations) {
            $currency = $currency ?? $customer->currency ?? $company->getDefaultCurrency();
            $exchangeRate = $this->getExchangeRate($currency, $company);

            $payment = new Payment([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'currency_id' => $currency->id,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'payment_number' => $paymentNumber ?? $this->generateNextPaymentNumber($company->id),
                'amount' => $amount->getAmount()->toFloat(),
                'exchange_rate' => $exchangeRate,
                'payment_date' => $paymentDate ?? now()->toDateString(),
                'status' => 'pending',
            ]);

            $payment->save();

            // Handle auto-allocation if requested
            if ($autoAllocate && $invoiceAllocations) {
                foreach ($invoiceAllocations as $invoiceId => $allocationAmount) {
                    if ($allocationAmount > 0) {
                        $invoice = Invoice::find($invoiceId);
                        if ($invoice && $invoice->company_id === $company->id && $invoice->customer_id === $customer->id) {
                            $payment->allocateToInvoice($invoice, Money::of($allocationAmount, $currency->code));
                        }
                    }
                }
            }

            return $payment->fresh(['customer', 'currency']);
        });

        $this->logAudit('payment.create', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'payment_method' => $paymentMethod,
            'amount' => $amount->getAmount()->toFloat(),
            'currency_id' => $currency->id,
            'payment_reference' => $result->payment_reference,
        ], auth()->user(), $company->id, $idempotencyKey, ['payment_id' => $result->id]);

        return $result;
    }

    public function processPayment(
        Payment $payment,
        ?string $processorReference = null,
        ?array $metadata = null
    ): Payment {
        $result = DB::transaction(function () use ($payment, $processorReference) {
            $payment->markAsCompleted($processorReference);

            // Auto-post completed payment to ledger
            try {
                app(\App\Services\LedgerIntegrationService::class)->postPaymentToLedger($payment);
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
        ], auth()->user(), $payment->company_id, result: ['processed_at' => now()->toISOString()]);

        return $result;
    }

    /**
     * Convenience overload: create + complete + optional auto-allocate + auto-post.
     */
    public function processIncomingPayment(
        Company $company,
        Customer $customer,
        float $amount,
        string $paymentMethod,
        ?string $paymentReference = null,
        ?string $paymentDate = null,
        ?Currency $currency = null,
        ?float $exchangeRate = null,
        ?string $notes = null,
        ?bool $autoAllocate = false,
        ?string $idempotencyKey = null
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
            idempotencyKey: $idempotencyKey
        );

        $payment = $this->processPayment($payment);

        if ($autoAllocate) {
            try { $this->autoAllocatePayment($payment); } catch (\Throwable $e) {
                Log::warning('Auto-allocate after process failed (non-fatal)', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment->fresh(['allocations.invoice']);
    }

    public function allocatePayment(
        Payment $payment,
        array $allocations,
        ?string $notes = null
    ): array {
        $result = DB::transaction(function () use ($payment, $allocations, $notes) {
            if (! $payment->canBeAllocated()) {
                throw new \InvalidArgumentException('Payment cannot be allocated');
            }

            $createdAllocations = [];
            $totalAllocationAmount = Money::of(0, $payment->currency->code);

            foreach ($allocations as $allocation) {
                $invoice = Invoice::find($allocation['invoice_id']);
                if (! $invoice || $invoice->company_id !== $payment->company_id) {
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
        ], auth()->user(), $payment->company_id, result: ['allocation_ids' => array_column($result, 'id')]);

        return $result;
    }

    public function autoAllocatePayment(Payment $payment): array
    {
        $result = DB::transaction(function () use ($payment) {
            return $payment->autoAllocate();
        });

        $this->logAudit('payment.auto_allocate', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'allocations_created' => count($result),
            'total_allocated' => array_sum(array_column($result, 'amount')),
        ], auth()->user(), $payment->company_id, result: ['allocation_ids' => array_column($result, 'id')]);

        return $result;
    }

    public function voidPayment(Payment $payment, ?string $reason = null): Payment
    {
        if (! $payment->canBeVoided()) {
            throw new \InvalidArgumentException('Payment cannot be voided');
        }

        $result = DB::transaction(function () use ($payment, $reason) {
            $payment->markAsCancelled($reason);

            return $payment->fresh();
        });

        $this->logAudit('payment.void', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'reason' => $reason,
        ], auth()->user(), $payment->company_id, result: ['voided_at' => $result->metadata['cancelled_at']]);

        return $result;
    }

    public function refundPayment(
        Payment $payment,
        Money $amount,
        ?string $reason = null
    ): array {
        $result = DB::transaction(function () use ($payment, $amount, $reason) {
            return $payment->refund($amount, $reason);
        });

        $this->logAudit('payment.refund', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'refund_amount' => $amount->getAmount()->toFloat(),
            'reason' => $reason,
        ], auth()->user(), $payment->company_id, result: ['refund_ids' => array_column($result, 'id')]);

        return $result;
    }

    public function voidAllocation(PaymentAllocation $allocation, ?string $reason = null): PaymentAllocation
    {
        if (! $allocation->canBeVoided()) {
            throw new \InvalidArgumentException('Allocation cannot be voided');
        }

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
        ], auth()->user(), $allocation->payment->company_id, result: ['voided_at' => $result->metadata['voided_at']]);

        return $result;
    }

    public function refundAllocation(
        PaymentAllocation $allocation,
        Money $amount,
        ?string $reason = null
    ): PaymentAllocation {
        if (! $allocation->canBeRefunded()) {
            throw new \InvalidArgumentException('Allocation cannot be refunded');
        }

        $result = DB::transaction(function () use ($allocation, $amount, $reason) {
            return $allocation->refund($amount, $reason);
        });

        $this->logAudit('payment.allocation.refund', [
            'allocation_id' => $allocation->id,
            'payment_id' => $allocation->payment_id,
            'invoice_id' => $allocation->invoice_id,
            'refund_amount' => $amount->getAmount()->toFloat(),
            'reason' => $reason,
        ], auth()->user(), $allocation->payment->company_id, result: ['refund_id' => $result->id]);

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
            'total_allocated' => $payments->sum(fn ($p) => $p->getAllocatedAmount()->getAmount()->toFloat()),
            'total_unallocated' => $payments->sum(fn ($p) => $p->getUnallocatedAmount()->getAmount()->toFloat()),
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
            'total_allocated' => $payments->sum(fn ($p) => $p->getAllocatedAmount()->getAmount()->toFloat()),
            'total_unallocated' => $payments->sum(fn ($p) => $p->getUnallocatedAmount()->getAmount()->toFloat()),
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
}
