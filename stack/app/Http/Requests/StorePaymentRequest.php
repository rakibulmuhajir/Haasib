<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $invoice = $this->route('invoice');

        if (!$invoice) {
            return false;
        }

        // Check if user has access to this invoice's company
        return $user->companies()->where('company_id', $invoice->company_id)->exists();
    }

    public function rules(): array
    {
        return [
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|string|in:cash,check,bank_transfer,credit_card,other',
            'amount' => 'required|numeric|min:0.01|max:99999999.9999',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'bank_account_id' => 'nullable|uuid|exists:bank_accounts,id',
            'payment_processor' => 'nullable|string|max:100',
            'processor_transaction_id' => 'nullable|string|max:100',
            'exchange_rate' => 'nullable|numeric|min:0.0001|max:999999',
            'fees' => 'nullable|numeric|min:0|max:999999.9999',
            'apply_to_invoices' => 'required|array|min:1',
            'apply_to_invoices.*.invoice_id' => 'required|uuid|exists:invoices,id',
            'apply_to_invoices.*.amount' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_date.required' => 'Payment date is required',
            'payment_date.date' => 'Please provide a valid payment date',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Payment method must be one of: cash, check, bank transfer, credit card, or other',
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a number',
            'amount.min' => 'Payment amount must be at least 0.01',
            'amount.max' => 'Payment amount cannot exceed 99,999,999.9999',
            'reference_number.max' => 'Reference number cannot exceed 100 characters',
            'notes.max' => 'Notes cannot exceed 1000 characters',
            'bank_account_id.uuid' => 'Bank account ID must be a valid UUID',
            'bank_account_id.exists' => 'Selected bank account does not exist',
            'payment_processor.max' => 'Payment processor cannot exceed 100 characters',
            'processor_transaction_id.max' => 'Processor transaction ID cannot exceed 100 characters',
            'exchange_rate.numeric' => 'Exchange rate must be a number',
            'exchange_rate.min' => 'Exchange rate must be greater than 0',
            'fees.numeric' => 'Fees must be a number',
            'fees.min' => 'Fees cannot be negative',
            'apply_to_invoices.required' => 'You must apply this payment to at least one invoice',
            'apply_to_invoices.array' => 'Invoice applications must be an array',
            'apply_to_invoices.min' => 'You must apply this payment to at least one invoice',
            'apply_to_invoices.*.invoice_id.required' => 'Invoice ID is required for each application',
            'apply_to_invoices.*.invoice_id.uuid' => 'Invoice ID must be a valid UUID',
            'apply_to_invoices.*.invoice_id.exists' => 'Selected invoice does not exist',
            'apply_to_invoices.*.amount.required' => 'Amount is required for each invoice application',
            'apply_to_invoices.*.amount.numeric' => 'Applied amount must be a number',
            'apply_to_invoices.*.amount.min' => 'Applied amount must be at least 0.01',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $totalPayment = $this->input('amount', 0);
            $totalApplied = collect($this->input('apply_to_invoices', []))->sum('amount');

            // Validate that total applied doesn't exceed payment amount
            if (abs($totalApplied - $totalPayment) > 0.01) {
                $validator->errors()->add('apply_to_invoices', 
                    'Total applied amount (' . number_format($totalApplied, 2) . 
                    ') must equal payment amount (' . number_format($totalPayment, 2) . ')');
            }

            // Validate invoice ownership and amounts
            $user = $this->user();
            foreach ($this->input('apply_to_invoices', []) as $index => $application) {
                if (!isset($application['invoice_id'])) {
                    continue;
                }

                // Check if user has access to this invoice
                $invoice = \App\Models\Invoice::find($application['invoice_id']);
                if (!$invoice || !$user->companies()->where('company_id', $invoice->company_id)->exists()) {
                    $validator->errors()->add("apply_to_invoices.{$index}.invoice_id", 
                        'You do not have access to this invoice');
                    continue;
                }

                // Check if applied amount exceeds remaining balance
                $remainingBalance = $invoice->total_amount - $invoice->total_paid;
                if ($application['amount'] > $remainingBalance) {
                    $validator->errors()->add("apply_to_invoices.{$index}.amount", 
                        'Applied amount cannot exceed remaining balance of ' . 
                        number_format($remainingBalance, 2));
                }
            }
        });
    }
}