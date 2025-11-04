<?php

namespace Modules\Accounting\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Comprehensive PaymentService - Handles all payment business logic
 *
 * This service consolidates payment functionality from multiple modules:
 * - Core payment processing and allocation
 * - Invoice payment processing
 * - Payment analytics and reporting
 * - Refund and reversal operations
 *
 * Features:
 * - ✅ Comprehensive transaction management
 * - ✅ Complete audit logging
 * - ✅ Idempotency support
 * - ✅ Multiple allocation strategies
 * - ✅ Business rule enforcement
 * - ✅ Schema-aligned with acct schema
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class PaymentService
{
    use AuditLogging;

    protected AllocationStrategyService $strategyService;
    protected PaymentAllocationService $allocationService;

    public function __construct(
        AllocationStrategyService $strategyService,
        PaymentAllocationService $allocationService
    ) {
        $this->strategyService = $strategyService;
        $this->allocationService = $allocationService;
    }

    /**
     * Record a standalone payment (optionally linked to an invoice)
     */
    public function recordPayment(array $data, ?Invoice $invoice = null): Payment
    {
        $validator = Validator::make($data, [
            'company_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'max:3'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_number' => ['nullable', 'string', 'max:50'],
            'created_by_user_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($validator, $invoice) {
            $payload = $validator->validated();

            // Generate payment number if not provided
            if (!isset($payload['payment_number'])) {
                $payload['payment_number'] = Payment::generatePaymentNumber($payload['company_id']);
            }

            $payload['status'] = 'completed';

            if ($invoice) {
                // Use direct relationship columns instead of polymorphic
                $payload['invoice_id'] = $invoice->id;

                // Validate payment doesn't exceed balance due
                if ($payload['amount'] > $invoice->balance_due) {
                    throw new \InvalidArgumentException(
                        "Payment amount exceeds invoice balance due: {$invoice->balance_due}"
                    );
                }
            }

            $payment = Payment::create($payload);

            if ($invoice) {
                $this->updateInvoiceAfterPayment($invoice, $payload['amount']);
            }

            // Log payment creation
            Log::info('Payment recorded successfully', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'customer_id' => $payment->customer_id,
                'invoice_id' => $invoice?->id,
            ]);

            return $payment;
        });
    }

    /**
     * Create a new payment with automatic allocation options
     */
    public function createPayment(
        Company $company,
        Customer $customer,
        array $paymentData,
        User $user,
        array $allocationOptions = []
    ): Payment {
        return DB::transaction(function () use ($company, $customer, $paymentData, $user, $allocationOptions) {
            // Generate payment number
            $paymentNumber = $paymentData['payment_number'] ?? Payment::generatePaymentNumber($company->id);

            // Create payment
            $payment = Payment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentData['payment_date'] ?? now(),
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $paymentData['reference_number'] ?? null,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? $company->currency ?? 'USD',
                'status' => 'pending',
                'notes' => $paymentData['notes'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            // Log payment creation
            Log::info('Payment created', [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'customer_id' => $customer->id,
                'user_id' => $user->id,
            ]);

            // Process automatic allocation if requested
            if (!empty($allocationOptions) && isset($allocationOptions['auto_allocate'])) {
                if (isset($allocationOptions['strategy'])) {
                    $allocations = $this->allocationService->applyAllocationStrategy(
                        $payment,
                        $allocationOptions['strategy'],
                        $user,
                        $allocationOptions['strategy_options'] ?? []
                    );

                    Log::info('Automatic allocation applied', [
                        'payment_id' => $payment->id,
                        'strategy' => $allocationOptions['strategy'],
                        'allocations_count' => count($allocations),
                    ]);
                }
            }

            return $payment;
        });
    }

    /**
     * Record a payment against an invoice with full audit trail
     */
    public function recordPaymentWithAudit(
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
                    'payment_method' => $method,
                    'reference_number' => $reference,
                    'notes' => $notes,
                    'payment_date' => $paymentDate ?? now()->toDateString(),
                    'idempotency_key' => $idempotencyKey,
                    'payment_number' => Payment::generatePaymentNumber($company->id),
                    'status' => 'completed',
                    'created_by_user_id' => $context->getUser()?->id,
                ]);

                if (! $payment->save()) {
                    throw new \RuntimeException('Failed to save payment: validation failed');
                }

                // Update invoice balance and status
                $this->updateInvoiceAfterPayment($invoice, $amount);

                return $payment->fresh();
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in recordPaymentWithAudit', [
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
     * Process payment completion and allocation
     */
    public function processPaymentCompletion(
        Payment $payment,
        User $user,
        array $allocationOptions = []
    ): array {
        return DB::transaction(function () use ($payment, $user, $allocationOptions) {
            // Mark payment as completed
            $payment->status = 'completed';
            $payment->save();

            // Process allocations
            $results = [];

            if ($payment->remaining_amount > 0) {
                if (isset($allocationOptions['manual_allocations'])) {
                    // Manual allocations
                    $results = $this->allocationService->allocatePaymentAcrossInvoices(
                        $payment,
                        $allocationOptions['manual_allocations'],
                        $user,
                        'manual'
                    );
                } elseif (isset($allocationOptions['strategy'])) {
                    // Automatic allocation with strategy
                    $results = $this->allocationService->applyAllocationStrategy(
                        $payment,
                        $allocationOptions['strategy'],
                        $user,
                        $allocationOptions['strategy_options'] ?? []
                    );
                }
            }

            return [
                'payment' => $payment->fresh(),
                'allocations' => $results,
                'remaining_amount' => $payment->fresh()->remaining_amount,
                'is_fully_allocated' => $payment->fresh()->is_fully_allocated,
            ];
        });
    }

    /**
     * Record a payment against multiple invoices
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
                        'payment_method' => $method,
                        'reference_number' => $reference."-{$idx}", // Add index to reference to make it unique
                        'notes' => $notes,
                        'payment_date' => $paymentDate ?? now()->toDateString(),
                        'idempotency_key' => $idempotencyKey."-{$idx}",
                        'payment_number' => Payment::generatePaymentNumber($company->id),
                        'status' => 'completed',
                        'created_by_user_id' => $context->getUser()?->id,
                    ]);

                    if (! $payment->save()) {
                        throw new \RuntimeException("Failed to save payment for invoice {$invoiceId}: validation failed");
                    }

                    // Update invoice balance and status
                    $this->updateInvoiceAfterPayment($invoice, $amount);

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
                'reference' => $payment->reference_number,
            ], $context);
        }

        return $results;
    }

    /**
     * Refund a payment
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
            if ($invoice) {
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
            }

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
     * Reverse a payment (cancel and refund)
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
            if ($invoice) {
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
            }

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
     * Mark payment as failed
     */
    public function markAsFailed(Payment $payment, ?User $performedBy = null): Payment
    {
        $payment->status = 'failed';
        $payment->save();

        Log::info('Payment marked as failed', [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'performed_by' => $performedBy?->id,
        ]);

        return $payment->fresh();
    }

    /**
     * Get comprehensive payment details with allocations
     */
    public function getPaymentDetails(Payment $payment): array
    {
        $payment->load(['activeAllocations.invoice.customer', 'allocatedInvoices']);

        return [
            'payment' => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'customer' => [
                    'id' => $payment->customer->id,
                    'name' => $payment->customer->name,
                ],
                'invoice' => $payment->invoice ? [
                    'id' => $payment->invoice->id,
                    'invoice_number' => $payment->invoice->invoice_number,
                    'total_amount' => $payment->invoice->total_amount,
                    'balance_due' => $payment->invoice->balance_due,
                ] : null,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'notes' => $payment->notes,
                'created_at' => $payment->created_at->toISOString(),
            ],
            'allocation_summary' => [
                'total_allocated' => $payment->total_allocated,
                'remaining_amount' => $payment->remaining_amount,
                'is_fully_allocated' => $payment->is_fully_allocated,
                'allocation_count' => $payment->activeAllocations()->count(),
            ],
            'allocations' => $this->allocationService->getPaymentAllocationSummary($payment),
            'unpaid_invoices' => $this->getUnpaidInvoicesForCustomer($payment->company, $payment->customer_id),
        ];
    }

    /**
     * Get available allocation strategies
     */
    public function getAvailableStrategies(): array
    {
        return $this->allocationService->getAvailableStrategies();
    }

    /**
     * Get customer payment summary
     */
    public function getCustomerPaymentSummary(Company $company, string $customerId): array
    {
        $payments = Payment::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->with(['activeAllocations'])
            ->get();

        $totalAmount = $payments->sum('amount');
        $totalAllocated = $payments->sum('total_allocated');
        $completedPayments = $payments->where('status', 'completed')->count();
        $fullyAllocatedPayments = $payments->filter(fn($p) => $p->is_fully_allocated)->count();

        return [
            'customer_id' => $customerId,
            'total_payments' => $payments->count(),
            'completed_payments' => $completedPayments,
            'fully_allocated_payments' => $fullyAllocatedPayments,
            'total_amount' => $totalAmount,
            'total_allocated' => $totalAllocated,
            'unallocated_amount' => $totalAmount - $totalAllocated,
            'allocation_rate' => $totalAmount > 0 ? round(($totalAllocated / $totalAmount) * 100, 2) : 0,
            'payment_details' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'total_allocated' => $payment->total_allocated,
                    'remaining_amount' => $payment->remaining_amount,
                    'is_fully_allocated' => $payment->is_fully_allocated,
                    'allocation_count' => $payment->activeAllocations()->count(),
                ];
            })->toArray(),
        ];
    }

    /**
     * List payments with pagination and filtering
     */
    public function listPayments(?string $companyId = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = Payment::query()->orderByDesc('payment_date');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get payments for a company with pagination and filtering
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
     */
    public function getPaymentsForInvoice(Invoice $invoice, ServiceContext $context)
    {
        $payments = Payment::where('invoice_id', $invoice->id)->get();

        $this->logAudit('invoice.payments_viewed', [
            'invoice_id' => $invoice->id,
            'payment_count' => $payments->count(),
        ], $context);

        return $payments;
    }

    /**
     * Batch process multiple payments with automatic allocation
     */
    public function batchProcessPayments(
        array $paymentIds,
        User $user,
        string $allocationStrategy = 'fifo',
        array $options = []
    ): array {
        return DB::transaction(function () use ($paymentIds, $user, $allocationStrategy, $options) {
            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($paymentIds as $paymentId) {
                try {
                    $payment = Payment::findOrFail($paymentId);

                    if ($payment->status !== 'completed') {
                        $processResult = $this->processPaymentCompletion($payment, $user, [
                            'strategy' => $allocationStrategy,
                            'strategy_options' => $options
                        ]);

                        $results[] = [
                            'payment_id' => $paymentId,
                            'status' => 'success',
                            'allocations_count' => count($processResult['allocations']),
                            'remaining_amount' => $processResult['remaining_amount'],
                        ];
                        $successCount++;
                    } else {
                        // Payment already completed, just check allocations
                        if ($payment->remaining_amount > 0) {
                            $allocations = $this->allocationService->applyAllocationStrategy(
                                $payment,
                                $allocationStrategy,
                                $user,
                                $options
                            );

                            $results[] = [
                                'payment_id' => $paymentId,
                                'status' => 'success',
                                'allocations_count' => count($allocations),
                                'remaining_amount' => $payment->fresh()->remaining_amount,
                            ];
                        } else {
                            $results[] = [
                                'payment_id' => $paymentId,
                                'status' => 'already_processed',
                                'message' => 'Payment was already fully allocated',
                            ];
                        }
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $results[] = [
                        'payment_id' => $paymentId,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                    $failureCount++;
                }
            }

            Log::info('Batch payment processing completed', [
                'total_payments' => count($paymentIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'strategy' => $allocationStrategy,
                'user_id' => $user->id,
            ]);

            return [
                'total_payments' => count($paymentIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'results' => $results,
            ];
        });
    }

    /**
     * Get payment analytics and insights
     */
    public function getPaymentAnalytics(
        Company $company,
        \DateTime $startDate,
        \DateTime $endDate
    ): array {
        $payments = Payment::where('company_id', $company->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $allocations = PaymentAllocation::where('company_id', $company->id)
            ->whereBetween('allocation_date', [$startDate, $endDate])
            ->get();

        return [
            'summary' => [
                'total_payments' => $payments->count(),
                'total_amount' => $payments->sum('amount'),
                'completed_payments' => $payments->where('status', 'completed')->count(),
                'total_allocated' => $allocations->sum('allocated_amount'),
                'allocation_rate' => $payments->sum('amount') > 0 ?
                    round(($allocations->sum('allocated_amount') / $payments->sum('amount')) * 100, 2) : 0,
            ],
            'by_payment_method' => $payments->groupBy('payment_method')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                        'allocated' => $group->sum(function ($payment) {
                            return $payment->activeAllocations()->sum('allocated_amount');
                        }),
                    ];
                })->toArray(),
            'by_status' => $payments->groupBy('status')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'amount' => $group->sum('amount'),
                    ];
                })->toArray(),
        ];
    }

    /**
     * Get payment reconciliation data
     */
    public function getPaymentReconciliation(Company $company, \DateTime $date): array
    {
        $payments = Payment::where('company_id', $company->id)
            ->whereDate('payment_date', $date)
            ->with(['activeAllocations.invoice'])
            ->get();

        $reconciliationData = $payments->map(function ($payment) {
            return [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'customer_name' => $payment->customer->name,
                'payment_amount' => $payment->amount,
                'allocated_amount' => $payment->total_allocated,
                'unallocated_amount' => $payment->remaining_amount,
                'allocations' => $payment->activeAllocations->map(function ($allocation) {
                    return [
                        'invoice_number' => $allocation->invoice->invoice_number,
                        'allocated_amount' => $allocation->allocated_amount,
                        'allocation_date' => $allocation->allocation_date->format('Y-m-d'),
                    ];
                })->toArray(),
            ];
        })->toArray();

        $totalPayments = $payments->sum('amount');
        $totalAllocated = $payments->sum('total_allocated');
        $totalUnallocated = $totalPayments - $totalAllocated;

        return [
            'reconciliation_date' => $date->format('Y-m-d'),
            'company_id' => $company->id,
            'summary' => [
                'total_payments' => $payments->count(),
                'total_payment_amount' => $totalPayments,
                'total_allocated' => $totalAllocated,
                'total_unallocated' => $totalUnallocated,
                'reconciliation_rate' => $totalPayments > 0 ? round(($totalAllocated / $totalPayments) * 100, 2) : 0,
            ],
            'details' => $reconciliationData,
            'discrepancies' => $this->findReconciliationDiscrepancies($payments),
        ];
    }

    /**
     * Get payment statistics
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
                'failed' => $payments->where('status', 'failed')->count(),
            ],
            'method_breakdown' => $payments->groupBy('payment_method')->map->count(),
        ];

        $this->logAudit('payment.statistics_viewed', [
            'company_id' => $company->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $context);

        return $stats;
    }

    /**
     * Helper method to update invoice after payment
     */
    private function updateInvoiceAfterPayment(Invoice $invoice, float $amount): void
    {
        $invoice->paid_amount += $amount;
        $invoice->balance_due = max(0, $invoice->total_amount - $invoice->paid_amount);

        // Update invoice status based on payment status
        if ($invoice->balance_due <= 0.001) { // Small tolerance for floating point errors
            $invoice->status = 'paid';
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        }

        $invoice->save();
    }

    /**
     * Helper method to get unpaid invoices for a customer
     */
    private function getUnpaidInvoicesForCustomer(Company $company, string $customerId): Collection
    {
        return Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Find reconciliation discrepancies
     */
    private function findReconciliationDiscrepancies(Collection $payments): array
    {
        $discrepancies = [];

        foreach ($payments as $payment) {
            // Check for over-allocation (allocated more than payment amount)
            if ($payment->total_allocated > $payment->amount) {
                $discrepancies[] = [
                    'type' => 'over_allocation',
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_amount' => $payment->amount,
                    'allocated_amount' => $payment->total_allocated,
                    'overage' => $payment->total_allocated - $payment->amount,
                ];
            }

            // Check for allocations to cancelled invoices
            $cancelledAllocations = $payment->activeAllocations()
                ->whereHas('invoice', function ($query) {
                    $query->where('status', 'cancelled');
                });

            if ($cancelledAllocations->count() > 0) {
                $discrepancies[] = [
                    'type' => 'cancelled_invoice_allocation',
                    'payment_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'allocated_amount' => $cancelledAllocations->sum('allocated_amount'),
                    'affected_invoices' => $cancelledAllocations->pluck('invoice.invoice_number')->toArray(),
                ];
            }
        }

        return $discrepancies;
    }
}