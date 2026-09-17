<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Http\Requests\Rules\RequiresBillCreatePermission;
use App\Modules\FuelStation\Http\Requests\Rules\RequiresCompletePurchaseRows;
use App\Modules\FuelStation\Http\Requests\Rules\RequiresSalesOrZeroConfirmation;

class StoreDailyCloseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return static::ruleSet();
    }

    /**
     * Plain rule set shared with SaveDailyCloseAction.
     *
     * IMPORTANT: this must stay a static/plain method, never resolved by
     * instantiating this FormRequest through the container (app(self::class)).
     * Container-resolving a FormRequest triggers Laravel's own
     * validateResolved() against the *current* HTTP request, which both
     * double-validates real HTTP submissions and throws a 422 for any
     * non-HTTP dispatch (CommandBus, Bus::dispatch, console, tests) that has
     * no current request to validate against.
     */
    public static function ruleSet(): array
    {
        $rules = [
            'intent' => 'nullable|in:park,post',
            'date' => 'required|date',

            // Tab 1: Sales (nozzle readings - each nozzle has electronic + optional manual readings)
            // 'present|array' allows a genuine zero-sales day; the
            // RequiresSalesOrZeroConfirmation rule below is what stops an
            // accidental empty *post* from permanently claiming the date
            // (park stays lenient).
            'nozzle_readings' => ['present', 'array', new RequiresSalesOrZeroConfirmation()],
            'zero_sales_confirmed' => 'nullable|boolean',
            'zero_sales_reason' => 'nullable|string|max:500|required_if:zero_sales_confirmed,1',
            'nozzle_readings.*.nozzle_id' => 'required|uuid',
            'nozzle_readings.*.item_id' => 'required|uuid',
            'nozzle_readings.*.opening_electronic' => 'required|numeric|min:0',
            'nozzle_readings.*.closing_electronic' => 'required|numeric|min:0',
            'nozzle_readings.*.opening_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.closing_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.liters_sold' => 'required|numeric|min:0',
            'nozzle_readings.*.sale_rate' => 'required|numeric|min:0',

            // Other sales (lubricants, etc.)
            'other_sales' => 'nullable|array',
            'other_sales.*.item_id' => 'required|uuid',
            'other_sales.*.item_name' => 'required|string|max:255',
            'other_sales.*.quantity' => 'required|numeric|min:0.001',
            'other_sales.*.unit_price' => 'required|numeric|min:0',
            'other_sales.*.amount' => 'required|numeric|min:0',

            // Tab 2: Tank readings
            'tank_readings' => 'nullable|array',
            'tank_readings.*.tank_id' => 'required|uuid',
            'tank_readings.*.stick_reading' => 'required|numeric|min:0',
            'tank_readings.*.liters' => 'required|numeric|min:0',

            // Tab 3: Money In
            'opening_cash' => 'required|numeric|min:0',
            'partner_deposits' => 'nullable|array',
            'partner_deposits.*.partner_id' => 'required|uuid',
            'partner_deposits.*.amount' => 'required|numeric|min:0',

            'amanat_deposits' => 'nullable|array',
            'amanat_deposits.*.customer_id' => 'required|uuid',
            'amanat_deposits.*.customer_name' => 'nullable|string|max:255',
            'amanat_deposits.*.amount' => 'required|numeric|min:0',
            'amanat_deposits.*.reference' => 'nullable|string|max:255',

            'other_deposits' => 'nullable|array',
            'other_deposits.*.deposit_type' => 'required|in:loss_compensation,fuel_disbursement,misc_income',
            'other_deposits.*.account_id' => 'nullable|uuid',
            'other_deposits.*.description' => 'nullable|string|max:255',
            'other_deposits.*.amount' => 'required|numeric|min:0',

            // Dynamic payment receipts (replaces hardcoded bank_transfers, card_swipes, parco_cards)
            'payment_receipts' => 'nullable|array',
            'payment_receipts.*.entries' => 'nullable|array',
            'payment_receipts.*.entries.*.reference' => 'nullable|string|max:255',
            'payment_receipts.*.entries.*.last_four' => 'nullable|string|max:4',
            'payment_receipts.*.entries.*.amount' => 'required|numeric|min:0',

            // Tab 4: Money Out
            'credit_sales' => 'nullable|array',
            'credit_sales.*.customer_id' => ['required', 'uuid', 'distinct', \Illuminate\Validation\Rule::exists(\App\Modules\Accounting\Models\Customer::class, 'id')->where('company_id', app(\App\Services\CurrentCompany::class)->get()->id)->where('is_active', true)],
            'credit_sales.*.amount' => 'required|numeric|min:0.01|decimal:0,2',
            'credit_sales.*.customer_name' => 'nullable|string|max:255',
            'credit_sales.*.reference' => 'nullable|string|max:100',
            'bank_deposits' => 'nullable|array',
            'bank_deposits.*.bank_account_id' => 'required|uuid',
            'bank_deposits.*.amount' => 'required|numeric|min:0',
            'bank_deposits.*.reference' => 'nullable|string|max:100',
            'bank_deposits.*.purpose' => 'nullable|string|max:255',

            'partner_withdrawals' => 'nullable|array',
            'partner_withdrawals.*.partner_id' => 'required|uuid',
            'partner_withdrawals.*.amount' => 'required|numeric|min:0',

            'employee_advances' => 'nullable|array',
            'employee_advances.*.employee_id' => 'required|uuid',
            'employee_advances.*.amount' => 'required|numeric|min:0',
            'employee_advances.*.reason' => 'nullable|string|max:255',

            'payroll_payouts' => 'nullable|array',
            'payroll_payouts.*.payslip_id' => 'required|uuid',
            'payroll_payouts.*.employee_id' => 'required|uuid',
            'payroll_payouts.*.amount' => 'required|numeric|min:0',

            'amanat_disbursements' => 'nullable|array',
            'amanat_disbursements.*.customer_id' => 'required|uuid',
            'amanat_disbursements.*.customer_name' => 'nullable|string|max:255',
            'amanat_disbursements.*.amount' => 'required|numeric|min:0',

            'expenses' => 'nullable|array',
            'expenses.*.account_id' => 'required|uuid',
            'expenses.*.description' => 'required|string|max:255',
            'expenses.*.amount' => 'required|numeric|min:0',

            // Supplier bills / fuel purchases entered inline, instead of via the Bills
            // module. Same shape for park (stored as-is) and post (each row must be
            // complete and becomes a canonical bill through Bill\CreateAction).
            'purchases' => ['nullable', 'array', new RequiresBillCreatePermission(), new RequiresCompletePurchaseRows()],
            'purchases.*.supplier_id' => 'nullable|uuid',
            'purchases.*.item_id' => 'nullable|uuid',
            'purchases.*.description' => 'nullable|string|max:255',
            'purchases.*.quantity' => 'nullable|numeric|min:0.01',
            'purchases.*.unit_cost' => 'nullable|numeric|min:0',
            'purchases.*.tank_id' => 'nullable|uuid',
            'purchases.*.supplier_invoice_number' => 'nullable|string|max:100',
            'purchases.*.notes' => 'nullable|string|max:500',
            'purchases.*.paid_now' => 'nullable|boolean',

            // Tab 5: Summary
            'closing_cash' => 'required|numeric|min:0',
            'cash_variance' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000',
        ];
        $companyId = app(\App\Services\CurrentCompany::class)->get()->id;
        foreach ([
            'partner_deposits.*.partner_id' => 'auth.partners', 'partner_withdrawals.*.partner_id' => 'auth.partners',
            'employee_advances.*.employee_id' => 'pay.employees', 'payroll_payouts.*.payslip_id' => 'pay.payslips', 'payroll_payouts.*.employee_id' => 'pay.employees',
            'nozzle_readings.*.nozzle_id' => 'fuel.nozzles',
            'nozzle_readings.*.item_id' => 'inv.items', 'other_sales.*.item_id' => 'inv.items',
            'tank_readings.*.tank_id' => 'inv.warehouses',
            'expenses.*.account_id' => 'acct.accounts', 'bank_deposits.*.bank_account_id' => 'acct.accounts',
            'amanat_deposits.*.customer_id' => 'acct.customers', 'amanat_disbursements.*.customer_id' => 'acct.customers',
        ] as $field => $table) {
            $rules[$field] = ['required', 'uuid', \Illuminate\Validation\Rule::exists($table, 'id')->where('company_id', $companyId)];
        }
        // Purchase rows are optional per-row (park stays lenient), but any supplier/item/tank
        // that is given must belong to this company.
        foreach ([
            'purchases.*.supplier_id' => 'acct.vendors', 'purchases.*.item_id' => 'inv.items', 'purchases.*.tank_id' => 'inv.warehouses',
        ] as $field => $table) {
            $rules[$field] = ['nullable', 'uuid', \Illuminate\Validation\Rule::exists($table, 'id')->where('company_id', $companyId)];
        }
        return $rules;
    }
}
