<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\Rule;

/**
 * Fuel sold straight from the supplier's tanker to a customer: never through a pump, never
 * into a tank. See FuelSaleController::storeDirect.
 */
class StoreDirectFuelSaleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_CREATE)
            && (! $this->boolean('paid_in_cash') || $this->hasCompanyPermission(Permissions::PAYMENT_CREATE))
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(\App\Services\CurrentCompany::class)->get()?->id;

        return [
            // Optional for a cash sale (walk-in customer); someone has to owe a credit sale.
            'customer_id' => ['nullable', 'required_if:paid_in_cash,false,0', 'uuid', Rule::exists(Customer::class, 'id')->where('company_id', $companyId)->where('is_active', true)],
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'paid_in_cash' => ['required', 'boolean'],
        ];
    }
}
