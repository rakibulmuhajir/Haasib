<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Payment;

class VoidPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('payments.void') && 
               $this->validateRlsContext() &&
               $this->validatePaymentAccess() &&
               $this->validateVoidable();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
            'void_date' => 'required|date|before_or_equal:today',
            'notify_customer' => 'boolean',
            'refund_method' => [
                'nullable',
                'string',
                'in:original,manual,check,cash,credit_note'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Void reason is required',
            'reason.max' => 'Void reason cannot exceed 500 characters',
            'void_date.required' => 'Void date is required',
            'void_date.date' => 'Void date must be a valid date',
            'void_date.before_or_equal' => 'Void date cannot be in the future',
            'refund_method.in' => 'Refund method must be one of: original, manual, check, cash, or credit note',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $payment = $this->getPayment();
            
            if (!$payment) {
                return;
            }

            // Validate void date
            $this->validateVoidDate($validator, $payment);
            
            // Validate business rules
            $this->validateVoidBusinessRules($validator, $payment);
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

    private function validateVoidable(): bool
    {
        $payment = $this->getPayment();
        
        if (!$payment) {
            return false;
        }

        return $payment->canBeVoided();
    }

    private function validateVoidDate($validator, Payment $payment): void
    {
        $voidDate = $this->input('void_date');
        $paymentDate = $payment->payment_date;
        
        if ($voidDate && $voidDate < $paymentDate) {
            $validator->errors()->add('void_date', 
                'Void date cannot be before the payment date');
        }
    }

    private function validateVoidBusinessRules($validator, Payment $payment): void
    {
        // Check for existing allocations
        $allocations = $payment->allocations()->count();
        if ($allocations > 0) {
            $validator->errors()->add('payment', 
                "Cannot void payment with {$allocations} existing allocation(s). " .
                'Please deallocate the payment first.');
        }

        // Check for existing refunds
        $refunds = $payment->refunds()->count();
        if ($refunds > 0) {
            $validator->errors()->add('payment', 
                "Cannot void payment with {$refunds} existing refund(s).");
        }

        // Check for existing reversal
        if ($payment->reversal()->exists()) {
            $validator->errors()->add('payment', 
                'Payment has already been reversed.');
        }

        // Check if payment is old (restrict voiding very old payments)
        $ninetyDaysAgo = now()->subDays(90);
        if ($payment->created_at < $ninetyDaysAgo) {
            if (!$this->hasCompanyPermission('payments.void_old')) {
                $validator->errors()->add('payment', 
                    'Cannot void payments older than 90 days without special permission');
            }
        }

        // Log voiding attempt for audit
        \Log::info('Payment void requested', [
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'amount' => $payment->amount,
            'user_id' => $this->user()->id,
            'reason' => $this->input('reason'),
            'audit_context' => $this->getAuditContext(),
        ]);
    }

    /**
     * Get the payment being voided
     */
    public function getPayment(): ?Payment
    {
        $paymentId = $this->route('payment');
        
        return Payment::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $paymentId)
            ->first();
    }

    /**
     * Get void reason for audit trail
     */
    public function getVoidReason(): string
    {
        return $this->input('reason');
    }

    /**
     * Get void date
     */
    public function getVoidDate(): string
    {
        return $this->input('void_date');
    }

    /**
     * Check if customer should be notified
     */
    public function shouldNotifyCustomer(): bool
    {
        return $this->boolean('notify_customer');
    }

    /**
     * Get refund method
     */
    public function getRefundMethod(): ?string
    {
        return $this->input('refund_method');
    }
}