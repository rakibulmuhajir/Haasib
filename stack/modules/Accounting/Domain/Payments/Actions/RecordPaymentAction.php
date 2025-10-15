<?php

namespace Modules\Accounting\Domain\Payments\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Telemetry\PaymentMetrics;
use App\Models\Payment;

class RecordPaymentAction
{
    /**
     * Execute the action to record a payment.
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validated = Validator::make($data, [
            'entity_id' => 'required|uuid', // customer_id in data model, entity_id in schema
            'payment_method' => 'required|string|in:cash,bank_transfer,card,cheque,other',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|uuid|exists:public.currencies,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'auto_allocate' => 'boolean',
            'allocation_strategy' => 'nullable|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'allocation_options' => 'nullable|array',
            'idempotency_key' => 'nullable|string|max:128',
        ])->validate();

        return DB::transaction(function () use ($validated) {
            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber($validated['company_id'] ?? null);

            // Create payment record
            $payment = Payment::create([
                'payment_id' => Str::uuid(),
                'company_id' => $validated['company_id'] ?? $this->getCurrentCompanyId(),
                'payment_number' => $paymentNumber,
                'payment_type' => 'customer_payment',
                'entity_type' => 'customer',
                'entity_id' => $validated['entity_id'],
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'currency_id' => $validated['currency_id'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'status' => 'pending',
                'created_by' => $validated['created_by_user_id'] ?? auth()->id(),
                'idempotency_key' => $validated['idempotency_key'],
            ]);

            // Emit event for telemetry
            event(new PaymentCreated([
                'payment_id' => $payment->payment_id,
                'company_id' => $payment->company_id,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'currency_id' => $validated['currency_id'],
                'entity_id' => $validated['entity_id'],
                'payment_number' => $paymentNumber,
            ]));

            // Emit audit event
            event(new PaymentAudited([
                'payment_id' => $payment->payment_id,
                'company_id' => $payment->company_id,
                'actor_id' => $validated['created_by_user_id'] ?? auth()->id(),
                'actor_type' => 'user',
                'action' => 'payment_created',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'payment_method' => $validated['payment_method'],
                    'amount' => $validated['amount'],
                    'currency_id' => $validated['currency_id'],
                    'entity_id' => $validated['entity_id'],
                    'payment_number' => $paymentNumber,
                    'reference_number' => $validated['reference_number'],
                    'auto_allocation_requested' => $validated['auto_allocate'] ?? false,
                    'allocation_strategy' => $validated['allocation_strategy'] ?? null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]));

            // Record metrics
            PaymentMetrics::paymentCreated(
                $payment->company_id,
                $validated['payment_method'],
                $validated['amount']
            );

            // Handle auto-allocation if requested
            if ($validated['auto_allocate'] ?? false) {
                // This will be implemented when we create the auto-allocation action
                // For now, just note that allocation was requested
                $payment->metadata = [
                    'auto_allocation_requested' => true,
                    'allocation_strategy' => $validated['allocation_strategy'],
                    'allocation_options' => $validated['allocation_options'] ?? [],
                ];
                $payment->save();
            }
            
            return [
                'payment_id' => $payment->payment_id,
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency_id' => $payment->currency_id,
                'message' => 'Payment recorded successfully',
            ];
        });
    }

    /**
     * Generate a unique payment number for the company.
     */
    private function generatePaymentNumber(?string $companyId): string
    {
        if (!$companyId) {
            $companyId = $this->getCurrentCompanyId();
        }

        $year = now()->format('Y');
        $sequence = DB::table('acct.payments')
            ->where('company_id', $companyId)
            ->whereYear('payment_date', $year)
            ->count() + 1;

        return "PAY-{$year}-" . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get current company ID from context.
     */
    private function getCurrentCompanyId(): ?string
    {
        return DB::select("SELECT current_setting('app.current_company', true) as company_id")[0]->company_id ?? null;
    }
}