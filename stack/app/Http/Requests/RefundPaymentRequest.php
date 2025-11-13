<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Payment;
use Illuminate\Validation\Rule;

class RefundPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('payments.refund') && 
               $this->validateRlsContext() &&
               $this->validatePaymentAccess() &&
               $this->validateRefundable();
    }

    public function rules(): array
    {
        $payment = $this->getPayment();
        $maxRefundAmount = $payment?->remaining_amount ?? 0;
        
        return [
            'refund_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . $maxRefundAmount,
            ],
            'refund_date' => 'required|date|before_or_equal:today',
            'refund_method' => [
                'required',
                'string',
                Rule::in(['original', 'cash', 'check', 'bank_transfer', 'credit_card', 'credit_note'])
            ],
            'reference_number' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:2000',
            
            // Refund allocations (optional - which invoices to apply refund to)
            'allocations' => 'nullable|array',
            'allocations.*.invoice_id' => [
                'required_with:allocations',
                'uuid',
                Rule::exists('acct.invoices', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            'allocations.*.amount' => [
                'required_with:allocations',
                'numeric',
                'min:0.01'
            ],
            
            // Bank transfer details
            'bank_account_id' => [
                'required_if:refund_method,bank_transfer',
                'nullable',
                'uuid',
                Rule::exists('bank_accounts', 'id')
            ],
            
            // Check details
            'check_number' => [
                'required_if:refund_method,check',
                'nullable',
                'string',
                'max:50'
            ],
            
            // Customer notification
            'notify_customer' => 'boolean',
            'email_message' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.required' => 'Refund amount is required',
            'refund_amount.numeric' => 'Refund amount must be a number',
            'refund_amount.min' => 'Refund amount must be at least 0.01',
            'refund_amount.max' => 'Refund amount cannot exceed remaining amount',
            'refund_date.required' => 'Refund date is required',
            'refund_date.date' => 'Refund date must be a valid date',
            'refund_date.before_or_equal' => 'Refund date cannot be in the future',
            'refund_method.required' => 'Refund method is required',
            'refund_method.in' => 'Refund method must be one of: original, cash, check, bank transfer, credit card, or credit note',
            'reference_number.max' => 'Reference number cannot exceed 100 characters',
            'reason.required' => 'Refund reason is required',
            'reason.max' => 'Refund reason cannot exceed 500 characters',
            'notes.max' => 'Notes cannot exceed 2000 characters',
            
            // Allocations
            'allocations.*.invoice_id.required_with' => 'Invoice ID is required for each allocation',
            'allocations.*.invoice_id.exists' => 'Selected invoice does not exist',
            'allocations.*.amount.required_with' => 'Refund amount is required for each allocation',
            'allocations.*.amount.min' => 'Refund amount must be at least 0.01',
            
            // Bank transfer
            'bank_account_id.required_if' => 'Bank account is required for bank transfer refunds',
            'bank_account_id.exists' => 'Selected bank account does not exist',
            
            // Check
            'check_number.required_if' => 'Check number is required for check refunds',
            'check_number.max' => 'Check number cannot exceed 50 characters',
            
            // Customer notification
            'email_message.max' => 'Email message cannot exceed 2000 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $payment = $this->getPayment();
            
            if (!$payment) {
                return;
            }

            // Validate refund date
            $this->validateRefundDate($validator, $payment);
            
            // Validate allocations
            $this->validateRefundAllocations($validator, $payment);
            
            // Validate business rules
            $this->validateRefundBusinessRules($validator, $payment);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notify_customer' => $this->boolean('notify_customer', false),
        ]);
    }

    private function validatePaymentAccess(): bool
    {
        $paymentId = $this->route('payment');
        
        return Payment::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $paymentId)
            ->exists();
    }

    private function validateRefundable(): bool
    {
        $payment = $this->getPayment();
        
        if (!$payment) {
            return false;
        }

        return $payment->canBeRefunded();
    }

    private function validateRefundDate($validator, Payment $payment): void
    {
        $refundDate = $this->input('refund_date');
        $paymentDate = $payment->payment_date;
        
        if ($refundDate && $refundDate < $paymentDate) {
            $validator->errors()->add('refund_date', 
                'Refund date cannot be before the payment date');
        }
    }

    private function validateRefundAllocations($validator, Payment $payment): void
    {
        $allocations = $this->input('allocations', []);
        $refundAmount = $this->input('refund_amount', 0);
        
        if (empty($allocations)) {
            return;
        }

        $totalAllocation = collect($allocations)->sum('amount');
        
        // Validate total allocation matches refund amount
        if (abs($totalAllocation - $refundAmount) > 0.01) {
            $validator->errors()->add('allocations', 
                'Total allocation amount (' . number_format($totalAllocation, 2) . 
                ') must equal refund amount (' . number_format($refundAmount, 2) . ')');
        }

        // Validate individual allocations
        foreach ($allocations as $index => $allocation) {
            $this->validateIndividualRefundAllocation($validator, $allocation, $index, $payment);
        }
    }

    private function validateIndividualRefundAllocation($validator, array $allocation, int $index, Payment $payment): void
    {
        if (!isset($allocation['invoice_id'])) {
            return;
        }

        // Get invoice
        $invoice = \App\Models\Acct\Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $allocation['invoice_id'])
            ->first();

        if (!$invoice) {
            $validator->errors()->add("allocations.{$index}.invoice_id", 
                'Selected invoice does not exist');
            return;
        }

        // Check if invoice was paid by this payment
        $wasPaidByThisPayment = $invoice->payments()
            ->where('payment_id', $payment->id)
            ->exists();

        if (!$wasPaidByThisPayment) {
            $validator->errors()->add("allocations.{$index}.invoice_id", 
                'Selected invoice was not paid by this payment');
        }

        // Check if refund amount exceeds what was paid
        $paymentForInvoice = $invoice->payments()
            ->where('payment_id', $payment->id)
            ->sum('amount');
            
        $alreadyRefundedForInvoice = $invoice->refunds()
            ->where('payment_id', $payment->id)
            ->sum('amount');

        $availableToRefund = $paymentForInvoice - $alreadyRefundedForInvoice;
        
        if ($allocation['amount'] > $availableToRefund + 0.01) {
            $validator->errors()->add("allocations.{$index}.amount", 
                'Refund amount (' . number_format($allocation['amount'], 2) . 
                ') cannot exceed available amount (' . number_format($availableToRefund, 2) . ')');
        }
    }

    private function validateRefundBusinessRules($validator, Payment $payment): void
    {
        // Check if payment is old (restrict refunding very old payments)
        $ninetyDaysAgo = now()->subDays(90);
        if ($payment->created_at < $ninetyDaysAgo) {
            if (!$this->hasCompanyPermission('payments.refund_old')) {
                $validator->errors()->add('payment', 
                    'Cannot refund payments older than 90 days without special permission');
            }
        }

        // Check for existing reversal
        if ($payment->reversal()->exists()) {
            $validator->errors()->add('payment', 
                'Cannot refund a payment that has been reversed');
        }

        // Validate refund method against original payment method
        $originalMethod = $payment->payment_method;
        $refundMethod = $this->input('refund_method');
        
        if ($this->isInvalidRefundMethod($originalMethod, $refundMethod)) {
            $validator->errors()->add('refund_method', 
                "Refund method '{$refundMethod}' is not compatible with original payment method '{$originalMethod}'");
        }

        // Log refund attempt for audit
        \Log::info('Payment refund requested', [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'refund_amount' => $this->input('refund_amount'),
            'refund_method' => $refundMethod,
            'user_id' => $this->user()->id,
            'reason' => $this->input('reason'),
            'audit_context' => $this->getAuditContext(),
        ]);
    }

    private function isInvalidRefundMethod(string $originalMethod, string $refundMethod): bool
    {
        // Allow same method or any of these flexible options
        $flexibleMethods = ['cash', 'check', 'credit_card', 'bank_transfer'];
        
        if ($originalMethod === $refundMethod) {
            return false;
        }
        
        // Original payment can be refunded in cash
        if (in_array($originalMethod, $flexibleMethods) && in_array($refundMethod, $flexibleMethods)) {
            return false;
        }
        
        // Credit notes can be used for any method
        if ($refundMethod === 'credit_note') {
            return false;
        }
        
        return true;
    }

    /**
     * Get the payment being refunded
     */
    public function getPayment(): ?Payment
    {
        $paymentId = $this->route('payment');
        
        return Payment::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $paymentId)
            ->first();
    }

    /**
     * Get refund details
     */
    public function getRefundDetails(): array
    {
        return [
            'amount' => $this->input('refund_amount'),
            'date' => $this->input('refund_date'),
            'method' => $this->input('refund_method'),
            'reference_number' => $this->input('reference_number'),
            'reason' => $this->input('reason'),
            'notes' => $this->input('notes'),
            'allocations' => $this->input('allocations', []),
            'bank_account_id' => $this->input('bank_account_id'),
            'check_number' => $this->input('check_number'),
            'notify_customer' => $this->boolean('notify_customer'),
            'email_message' => $this->input('email_message'),
        ];
    }
}