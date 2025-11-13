<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('expenses.create') && 
               $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            // Basic expense information
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')
            ],
            'description' => 'required|string|max:1000',
            'reference' => 'nullable|string|max:100',
            
            // Expense categorization
            'expense_category_id' => [
                'nullable',
                'uuid',
                Rule::exists('expense_categories', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            'account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            
            // Employee information (if company-paid expense)
            'employee_id' => [
                'nullable',
                'uuid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->whereHas('companies', function ($q) {
                        $q->where('company_id', $this->getCurrentCompanyId());
                    });
                })
            ],
            
            // Vendor information (if vendor expense)
            'vendor_id' => [
                'nullable',
                'uuid',
                Rule::exists('vendors', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            'vendor_name' => 'nullable|string|max:255',
            
            // Payment information
            'payment_method' => [
                'required',
                'string',
                Rule::in(['cash', 'check', 'credit_card', 'debit_card', 'bank_transfer', 'company_card', 'reimbursable'])
            ],
            'payment_status' => [
                'nullable',
                'string',
                Rule::in(['pending', 'paid', 'reimbursed'])
            ],
            'paid_by' => [
                'nullable',
                'string',
                Rule::in(['employee', 'company'])
            ],
            
            // Receipt information
            'receipt_required' => 'boolean',
            'receipt_attached' => 'boolean',
            'receipt_number' => 'nullable|string|max:100',
            
            // Tax information
            'tax_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'tax_inclusive' => 'boolean',
            
            // Additional details
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            
            // Project/department tracking
            'project_id' => [
                'nullable',
                'uuid',
                Rule::exists('projects', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            'department' => 'nullable|string|max:100',
            
            // Mileage (if applicable)
            'mileage_start' => 'nullable|numeric|min:0|max:999999',
            'mileage_end' => 'nullable|numeric|min:0|max:999999',
            'mileage_rate' => 'nullable|numeric|min:0|max:999.99',
            'mileage_total' => 'nullable|numeric|min:0|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            // Basic information
            'date.required' => 'Expense date is required',
            'date.before_or_equal' => 'Expense date cannot be in the future',
            'amount.required' => 'Expense amount is required',
            'amount.numeric' => 'Expense amount must be a number',
            'amount.min' => 'Expense amount must be greater than 0',
            'amount.max' => 'Expense amount cannot exceed $999,999,999.99',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',
            'description.required' => 'Description is required',
            'description.max' => 'Description cannot exceed 1000 characters',
            'reference.max' => 'Reference cannot exceed 100 characters',
            
            // Categorization
            'expense_category_id.exists' => 'Selected expense category is invalid',
            'account_id.exists' => 'Selected account is invalid',
            
            // Employee/Vendor
            'employee_id.exists' => 'Selected employee is invalid',
            'vendor_id.exists' => 'Selected vendor is invalid',
            'vendor_name.max' => 'Vendor name cannot exceed 255 characters',
            
            // Payment
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'payment_status.in' => 'Invalid payment status selected',
            'paid_by.in' => 'Invalid paid by option selected',
            
            // Receipt
            'receipt_number.max' => 'Receipt number cannot exceed 100 characters',
            
            // Tax
            'tax_amount.numeric' => 'Tax amount must be a number',
            'tax_amount.min' => 'Tax amount cannot be negative',
            'tax_amount.max' => 'Tax amount cannot exceed $999,999,999.99',
            
            // Notes
            'notes.max' => 'Notes cannot exceed 2000 characters',
            
            // Tags
            'tags.max' => 'Cannot add more than 10 tags',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
            
            // Project/Department
            'project_id.exists' => 'Selected project is invalid',
            'department.max' => 'Department cannot exceed 100 characters',
            
            // Mileage
            'mileage_start.numeric' => 'Mileage start must be a number',
            'mileage_end.numeric' => 'Mileage end must be a number',
            'mileage_rate.numeric' => 'Mileage rate must be a number',
            'mileage_total.numeric' => 'Mileage total must be a number',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateBusinessLogic($validator);
            $this->validateMileage($validator);
            $this->validatePaymentLogic($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        
        // Set default values
        $this->merge([
            'payment_status' => $this->input('payment_status', 'pending'),
            'currency' => $this->input('currency', 'USD'),
            'receipt_required' => $this->boolean('receipt_required', false),
            'receipt_attached' => $this->boolean('receipt_attached', false),
            'tax_inclusive' => $this->boolean('tax_inclusive', false),
        ]);
    }

    private function validateBusinessLogic($validator): void
    {
        $paidBy = $this->input('paid_by');
        $employeeId = $this->input('employee_id');
        $vendorId = $this->input('vendor_id');
        $vendorName = $this->input('vendor_name');
        
        // If expense is paid by employee, employee is required
        if ($paidBy === 'employee' && !$employeeId) {
            $validator->errors()->add('employee_id', 
                'Employee is required when expense is paid by employee');
        }
        
        // If expense has vendor ID or name, validate at least one exists
        if ($vendorId || $vendorName) {
            // This is valid - vendor expense
        } else {
            // If no vendor, this might be an employee expense
            if (!$employeeId && $paidBy !== 'company') {
                $validator->errors()->add('vendor_id', 
                    'Either vendor or employee information is required');
            }
        }
        
        // Validate date is not too far in the past
        $date = $this->input('date');
        if ($date) {
            $maxPastDays = 365;
            $daysAgo = date_diff(date_create($date), date_create('today'))->days;
            
            if ($daysAgo > $maxPastDays) {
                $validator->errors()->add('date', 
                    "Expense date cannot be more than {$maxPastDays} days in the past");
            }
        }
    }

    private function validateMileage($validator): void
    {
        $hasMileage = $this->hasAny(['mileage_start', 'mileage_end', 'mileage_rate', 'mileage_total']);
        
        if ($hasMileage) {
            $start = $this->input('mileage_start');
            $end = $this->input('mileage_end');
            $rate = $this->input('mileage_rate');
            $total = $this->input('mileage_total');
            
            // If mileage info is provided, all fields should be present
            if ($start !== null && $end !== null) {
                if ($end < $start) {
                    $validator->errors()->add('mileage_end', 
                        'End mileage cannot be less than start mileage');
                }
            }
            
            // Validate calculated total matches provided total
            if ($start !== null && $end !== null && $rate !== null && $total !== null) {
                $calculatedTotal = ($end - $start) * $rate;
                if (abs($calculatedTotal - $total) > 0.01) {
                    $validator->errors()->add('mileage_total', 
                        'Mileage total does not match calculated amount');
                }
            }
        }
    }

    private function validatePaymentLogic($validator): void
    {
        $paymentMethod = $this->input('payment_method');
        $paidBy = $this->input('paid_by');
        
        // Validate payment method combinations
        if ($paidBy === 'employee' && $paymentMethod === 'company_card') {
            $validator->errors()->add('payment_method', 
                'Company card cannot be used for employee-paid expenses');
        }
        
        if ($paidBy === 'company' && $paymentMethod === 'reimbursable') {
            $validator->errors()->add('payment_method', 
                'Reimbursable method cannot be used for company-paid expenses');
        }
        
        // Log expense creation for audit
        \Log::info('Expense creation requested', [
            'amount' => $this->input('amount'),
            'currency' => $this->input('currency'),
            'description' => $this->input('description'),
            'payment_method' => $paymentMethod,
            'paid_by' => $paidBy,
            'user_id' => $this->user()?->id,
            'company_id' => $this->getCurrentCompanyId(),
            'audit_context' => $this->getAuditContext(),
        ]);
    }

    /**
     * Get expense data for processing
     */
    public function getExpenseData(): array
    {
        return [
            'date' => $this->input('date'),
            'amount' => floatval($this->input('amount')),
            'currency' => $this->input('currency'),
            'description' => $this->input('description'),
            'reference' => $this->input('reference'),
            'expense_category_id' => $this->input('expense_category_id'),
            'account_id' => $this->input('account_id'),
            'employee_id' => $this->input('employee_id'),
            'vendor_id' => $this->input('vendor_id'),
            'vendor_name' => $this->input('vendor_name'),
            'payment_method' => $this->input('payment_method'),
            'payment_status' => $this->input('payment_status', 'pending'),
            'paid_by' => $this->input('paid_by'),
            'receipt_required' => $this->boolean('receipt_required'),
            'receipt_attached' => $this->boolean('receipt_attached'),
            'receipt_number' => $this->input('receipt_number'),
            'tax_amount' => $this->input('tax_amount') ? floatval($this->input('tax_amount')) : null,
            'tax_inclusive' => $this->boolean('tax_inclusive'),
            'notes' => $this->input('notes'),
            'tags' => $this->input('tags', []),
            'project_id' => $this->input('project_id'),
            'department' => $this->input('department'),
            'mileage_start' => $this->input('mileage_start'),
            'mileage_end' => $this->input('mileage_end'),
            'mileage_rate' => $this->input('mileage_rate'),
            'mileage_total' => $this->input('mileage_total'),
            'company_id' => $this->getCurrentCompanyId(),
            'created_by' => $this->user()?->id,
        ];
    }

    /**
     * Check if expense requires approval
     */
    public function requiresApproval(): bool
    {
        $amount = floatval($this->input('amount', 0));
        
        // Expenses over certain amount require approval
        return $amount > 1000;
    }

    /**
     * Check if expense is tax deductible
     */
    public function isTaxDeductible(): bool
    {
        $businessExpenseCategories = ['travel', 'meals', 'supplies', 'software', 'training'];
        
        return in_array($this->input('expense_category_id'), $businessExpenseCategories) ||
               $this->input('account_id') !== null;
    }
}