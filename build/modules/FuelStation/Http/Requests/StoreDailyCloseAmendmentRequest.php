<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreDailyCloseAmendmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_AMEND)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'amendment_reason' => ['required', 'string', 'min:10', 'max:500'],
            'date' => ['required', 'date'],

            // Tab 1: Sales
            'nozzle_readings' => ['required', 'array', 'min:1'],
            'nozzle_readings.*.nozzle_id' => ['required', 'uuid'],
            'nozzle_readings.*.item_id' => ['required', 'uuid'],
            'nozzle_readings.*.opening_electronic' => ['required', 'numeric', 'min:0'],
            'nozzle_readings.*.closing_electronic' => ['required', 'numeric', 'min:0'],
            'nozzle_readings.*.opening_manual' => ['nullable', 'numeric', 'min:0'],
            'nozzle_readings.*.closing_manual' => ['nullable', 'numeric', 'min:0'],
            'nozzle_readings.*.liters_sold' => ['required', 'numeric', 'min:0'],
            'nozzle_readings.*.sale_rate' => ['required', 'numeric', 'min:0'],

            'other_sales' => ['nullable', 'array'],
            'other_sales.*.item_id' => ['required', 'uuid'],
            'other_sales.*.item_name' => ['required', 'string', 'max:255'],
            'other_sales.*.quantity' => ['required', 'integer', 'min:1'],
            'other_sales.*.unit_price' => ['required', 'numeric', 'min:0'],
            'other_sales.*.amount' => ['required', 'numeric', 'min:0'],

            // Tab 2: Tank readings
            'tank_readings' => ['nullable', 'array'],
            'tank_readings.*.tank_id' => ['required', 'uuid'],
            'tank_readings.*.stick_reading' => ['required', 'numeric', 'min:0'],
            'tank_readings.*.liters' => ['required', 'numeric', 'min:0'],

            // Tab 3: Money In
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'partner_deposits' => ['nullable', 'array'],
            'partner_deposits.*.partner_id' => ['required', 'uuid'],
            'partner_deposits.*.amount' => ['required', 'numeric', 'min:0'],

            'payment_receipts' => ['nullable', 'array'],
            'payment_receipts.*.entries' => ['nullable', 'array'],
            'payment_receipts.*.entries.*.reference' => ['nullable', 'string', 'max:255'],
            'payment_receipts.*.entries.*.last_four' => ['nullable', 'string', 'max:4'],
            'payment_receipts.*.entries.*.amount' => ['required', 'numeric', 'min:0'],

            // Tab 4: Money Out
            'bank_deposits' => ['nullable', 'array'],
            'bank_deposits.*.bank_account_id' => ['required', 'uuid'],
            'bank_deposits.*.amount' => ['required', 'numeric', 'min:0'],
            'bank_deposits.*.reference' => ['nullable', 'string', 'max:100'],
            'bank_deposits.*.purpose' => ['nullable', 'string', 'max:255'],

            'partner_withdrawals' => ['nullable', 'array'],
            'partner_withdrawals.*.partner_id' => ['required', 'uuid'],
            'partner_withdrawals.*.amount' => ['required', 'numeric', 'min:0'],

            'employee_advances' => ['nullable', 'array'],
            'employee_advances.*.employee_id' => ['required', 'uuid'],
            'employee_advances.*.amount' => ['required', 'numeric', 'min:0'],
            'employee_advances.*.reason' => ['nullable', 'string', 'max:255'],

            'amanat_disbursements' => ['nullable', 'array'],
            'amanat_disbursements.*.customer_name' => ['required', 'string', 'max:255'],
            'amanat_disbursements.*.amount' => ['required', 'numeric', 'min:0'],

            'expenses' => ['nullable', 'array'],
            'expenses.*.account_id' => ['required', 'uuid'],
            'expenses.*.description' => ['required', 'string', 'max:255'],
            'expenses.*.amount' => ['required', 'numeric', 'min:0'],

            // Tab 5: Summary
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'cash_variance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
