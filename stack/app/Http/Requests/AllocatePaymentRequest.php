<?php

namespace App\Http\Requests;

use App\Models\Acct\Payment;
use Illuminate\Validation\Rule;

class AllocatePaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('payments.allocate') &&
               $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'payment_id' => [
                'required',
                'uuid',
                Rule::exists('acct.payments', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                        ->where('status', Payment::STATUS_COMPLETED);
                }),
            ],
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => [
                'required',
                'uuid',
                Rule::exists('acct.invoices', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                }),
            ],
            'allocations.*.amount' => 'required|numeric|min:0.01|max:99999999.9999',
            'allocations.*.allocation_date' => 'required|date|before_or_equal:today',
            'allocations.*.notes' => 'nullable|string|max:500',
            'auto_allocate' => 'boolean',
            'allocation_method' => [
                'nullable',
                'string',
                Rule::in(['fifo', 'lifo', 'pro_rata', 'manual']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_id.required' => 'Payment ID is required',
            'payment_id.exists' => 'Payment must exist and be completed in your company',
            'allocations.required' => 'At least one allocation is required',
            'allocations.min' => 'At least one allocation is required',
            'allocations.*.invoice_id.required' => 'Invoice ID is required for each allocation',
            'allocations.*.invoice_id.exists' => 'Selected invoice does not exist in your company',
            'allocations.*.amount.required' => 'Allocation amount is required',
            'allocations.*.amount.min' => 'Allocation amount must be at least 0.01',
            'allocations.*.amount.max' => 'Allocation amount cannot exceed 99,999,999.9999',
            'allocations.*.allocation_date.required' => 'Allocation date is required',
            'allocations.*.allocation_date.date' => 'Allocation date must be a valid date',
            'allocations.*.allocation_date.before_or_equal' => 'Allocation date cannot be in the future',
            'allocations.*.notes.max' => 'Allocation notes cannot exceed 500 characters',
            'allocation_method.in' => 'Allocation method must be one of: fifo, lifo, pro_rata, or manual',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $payment = $this->getPayment();

            if (! $payment) {
                return;
            }

            // Validate that payment can be allocated
            if (! $payment->canBeAllocated()) {
                $validator->errors()->add('payment_id',
                    'This payment cannot be allocated. It must be completed and have remaining amount.');
            }

            // Get total allocation amount
            $totalAllocation = collect($this->input('allocations', []))->sum('amount');
            $remainingAmount = $payment->remaining_amount;

            // Validate that total allocation doesn't exceed remaining amount
            if ($totalAllocation > $remainingAmount + 0.01) { // Allow small rounding difference
                $validator->errors()->add('allocations',
                    'Total allocation amount ('.number_format($totalAllocation, 2).
                    ') cannot exceed remaining payment amount ('.number_format($remainingAmount, 2).')');
            }

            // Validate individual allocations
            foreach ($this->input('allocations', []) as $index => $allocation) {
                $this->validateIndividualAllocation($validator, $allocation, $index, $payment);
            }

            // Validate no duplicate invoice allocations
            $this->validateNoDuplicateInvoices($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_allocate' => $this->boolean('auto_allocate', false),
        ]);
    }

    private function getPayment(): ?Payment
    {
        $paymentId = $this->input('payment_id');
        if (! $paymentId) {
            return null;
        }

        return Payment::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $paymentId)
            ->first();
    }

    private function validateIndividualAllocation($validator, array $allocation, int $index, Payment $payment): void
    {
        if (! isset($allocation['invoice_id'])) {
            return;
        }

        // Get invoice
        $invoice = \App\Models\Acct\Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $allocation['invoice_id'])
            ->first();

        if (! $invoice) {
            $validator->errors()->add("allocations.{$index}.invoice_id",
                'Selected invoice does not exist');

            return;
        }

        // Check if invoice can accept payments
        if ($invoice->status !== 'sent' && $invoice->status !== 'partial') {
            $validator->errors()->add("allocations.{$index}.invoice_id",
                'Payment can only be allocated to invoices with status "sent" or "partial"');
        }

        // Check if allocation amount exceeds remaining balance
        $remainingBalance = $invoice->total_amount - $invoice->total_paid;
        if ($allocation['amount'] > $remainingBalance + 0.01) { // Allow small rounding difference
            $validator->errors()->add("allocations.{$index}.amount",
                'Allocation amount ('.number_format($allocation['amount'], 2).
                ') cannot exceed invoice remaining balance ('.number_format($remainingBalance, 2).')');
        }

        // Validate allocation date
        if (isset($allocation['allocation_date'])) {
            $allocationDate = \Carbon\Carbon::parse($allocation['allocation_date']);
            $invoiceDate = \Carbon\Carbon::parse($invoice->issue_date);
            $paymentDate = \Carbon\Carbon::parse($payment->payment_date);

            if ($allocationDate->lt($invoiceDate)) {
                $validator->errors()->add("allocations.{$index}.allocation_date",
                    'Allocation date cannot be before invoice issue date');
            }

            if ($allocationDate->gt($paymentDate)) {
                $validator->errors()->add("allocations.{$index}.allocation_date",
                    'Allocation date cannot be after payment date');
            }
        }
    }

    private function validateNoDuplicateInvoices($validator): void
    {
        $invoiceIds = collect($this->input('allocations', []))->pluck('invoice_id');
        $duplicates = $invoiceIds->duplicates();

        if ($duplicates->isNotEmpty()) {
            $validator->errors()->add('allocations',
                'Cannot allocate the same invoice multiple times. Duplicate invoice IDs: '.$duplicates->join(', '));
        }
    }
}
