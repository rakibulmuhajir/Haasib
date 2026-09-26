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
            'nozzle_readings.*.meter_rolled_over' => 'nullable|boolean',
            'nozzle_readings.*.opening_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.closing_manual' => 'nullable|numeric|min:0',
            'nozzle_readings.*.liters_sold' => 'required|numeric|min:0',
            'nozzle_readings.*.sale_rate' => 'required|numeric|min:0',
            // Litres run through the meter for a calibration test and poured straight back into
            // the tank - not a sale. See DailyCloseService::litresFromReadings.
            'nozzle_readings.*.returned_liters' => 'nullable|numeric|min:0',

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
            'amanat_deposits.*.payment_account_id' => 'nullable|string',
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
            // Optional fuel + litres on a manual row, so a per-litre customer discount can be
            // priced (see DailyCloseCreditSaleService::prepare, which refuses a per_litre
            // discount without litres); a percent discount needs only the amount above.
            'credit_sales.*.item_id' => 'nullable|uuid',
            'credit_sales.*.litres' => 'nullable|numeric|min:0.01',
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
            'amanat_disbursements.*.payment_account_id' => 'nullable|string',

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
            // The total actually billed, when the supplier's rate carries more decimals
            // than the row's own unit_cost field does -- see Bill\BillLineTotals.
            'purchases.*.line_total' => 'nullable|numeric|min:0|decimal:0,2',
            'purchases.*.tank_id' => 'nullable|uuid',
            'purchases.*.supplier_invoice_number' => 'nullable|string|max:100',
            'purchases.*.notes' => 'nullable|string|max:500',
            'purchases.*.paid_now' => 'nullable|boolean',

            'bank_withdrawals' => 'nullable|array',
            'bank_withdrawals.*.bank_account_id' => ['required', 'uuid', \Illuminate\Validation\Rule::exists(\App\Modules\Accounting\Models\Account::class, 'id')
                ->where('company_id', app(\App\Services\CurrentCompany::class)->get()->id)
                ->where('is_active', true)->where('subtype', 'bank')->whereNull('deleted_at')],
            'bank_withdrawals.*.amount' => 'required|numeric|min:0.01',
            'bank_withdrawals.*.reference' => 'nullable|string|max:255',
            'bank_withdrawals.*.purpose' => 'nullable|string|max:255',

            // Payments received: a buyer settling an invoice, entered inline instead of at
            // /payments. Same shape for park and post; on post each row must be complete
            // (see RequiresCompletePaymentsReceivedRows) and becomes a canonical payment
            // through Payment\CreateAction dispatched via the CommandBus.
            'payments_received' => ['nullable', 'array', new \App\Modules\FuelStation\Http\Requests\Rules\RequiresCompletePaymentsReceivedRows()],
            'payments_received.*.customer_id' => 'nullable|uuid',
            'payments_received.*.customer_name' => 'nullable|string|max:255',
            'payments_received.*.invoice_id' => 'nullable|uuid',
            'payments_received.*.invoice_number' => 'nullable|string|max:100',
            'payments_received.*.amount' => 'nullable|numeric|min:0.01',
            'payments_received.*.payment_account_id' => 'nullable|uuid',
            'payments_received.*.reference' => 'nullable|string|max:100',

            // "Pay supplier" rows entered inline in Cash Out instead of at /bill-payments.
            // Same shape for park and post; on post each row must be complete (see
            // RequiresCompletePaySupplierRows) and becomes a canonical bill payment through
            // bill_payment.create dispatched via the CommandBus (DailyClosePaySupplierService).
            'pay_suppliers' => ['nullable', 'array', new \App\Modules\FuelStation\Http\Requests\Rules\RequiresCompletePaySupplierRows()],
            'pay_suppliers.*.vendor_id' => 'nullable|uuid',
            'pay_suppliers.*.vendor_name' => 'nullable|string|max:255',
            'pay_suppliers.*.amount' => 'nullable|numeric|min:0.01',
            'pay_suppliers.*.payment_account_id' => 'nullable|uuid',
            'pay_suppliers.*.reference' => 'nullable|string|max:100',

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
        foreach (['amanat_deposits.*.payment_account_id', 'amanat_disbursements.*.payment_account_id'] as $field) {
            $rules[$field] = ['nullable', 'string'];
        }
        // Purchase rows are optional per-row (park stays lenient), but any supplier/item/tank
        // that is given must belong to this company.
        foreach ([
            'purchases.*.supplier_id' => 'acct.vendors', 'purchases.*.item_id' => 'inv.items', 'purchases.*.tank_id' => 'inv.warehouses',
            'payments_received.*.customer_id' => 'acct.customers', 'payments_received.*.invoice_id' => 'acct.invoices',
            'pay_suppliers.*.vendor_id' => 'acct.vendors',
            'credit_sales.*.item_id' => 'inv.items',
        ] as $field => $table) {
            $rules[$field] = ['nullable', 'uuid', \Illuminate\Validation\Rule::exists($table, 'id')->where('company_id', $companyId)];
        }
        // The account a payment was received into, or a supplier was paid from, must be this
        // company's own cash or bank account.
        foreach (['payments_received.*.payment_account_id', 'pay_suppliers.*.payment_account_id'] as $field) {
            $rules[$field] = ['nullable', 'uuid', \Illuminate\Validation\Rule::exists(\App\Modules\Accounting\Models\Account::class, 'id')
                ->where('company_id', $companyId)->where('is_active', true)->whereIn('subtype', ['cash', 'bank'])->whereNull('deleted_at')];
        }
        return $rules;
    }

    /**
     * Refuse impossible meter readings before anything is posted, with the message beside the
     * field. The rule itself lives in DailyCloseService::meterReadingProblem(), which the close
     * also enforces for every caller - this only makes sure a web user hears it early.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {

            foreach ((array) $this->input('nozzle_readings', []) as $i => $reading) {
                $opening = $reading['opening_electronic'] ?? null;
                $closing = $reading['closing_electronic'] ?? null;

                if (! is_numeric($opening) || ! is_numeric($closing)) {
                    continue; // the field rules already report this
                }

                $problem = \App\Modules\FuelStation\Services\DailyCloseService::meterReadingProblem(
                    (float) $opening,
                    (float) $closing,
                    filter_var($reading['meter_rolled_over'] ?? false, FILTER_VALIDATE_BOOLEAN),
                );

                if ($problem !== null) {
                    $validator->errors()->add("nozzle_readings.{$i}.closing_electronic", $problem);

                    continue;
                }

                $returned = $reading['returned_liters'] ?? null;
                if (is_numeric($returned)) {
                    $rolledOver = filter_var($reading['meter_rolled_over'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $meterLiters = \App\Modules\FuelStation\Services\DailyCloseService::litresFromMeters(
                        (float) $opening, (float) $closing, $rolledOver
                    );

                    if ((float) $returned > $meterLiters) {
                        $validator->errors()->add(
                            "nozzle_readings.{$i}.returned_liters",
                            'Returned litres ('.$returned.' L) cannot exceed the litres the meter moved ('.$meterLiters.' L).'
                        );
                    }
                }
            }
        });
    }
}
