<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A delivery entered on its own, outside a daily close: it becomes a bill whose litres are
 * received into the tank when that day's close is posted. A day already closed is refused --
 * receiving litres behind a posted close would put its dip out of step.
 */
class StoreFuelDeliveryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()->id;

        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'supplier_id' => ['required', 'uuid', Rule::exists(Vendor::class, 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')->where('company_id', $companyId)],
            'tank_id' => ['nullable', 'uuid', Rule::exists(Warehouse::class, 'id')->where('company_id', $companyId)->where('warehouse_type', 'tank')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'direct_quantity' => ['nullable', 'numeric', 'min:0', 'lte:quantity'],
            'unit_cost' => ['nullable', 'numeric', 'gt:0', 'required_without:line_total'],
            'line_total' => ['nullable', 'numeric', 'gt:0', 'required_without:unit_cost'],
            'paid_now' => ['boolean'],
            'payment_account_id' => [
                'nullable', 'required_if:paid_now,true', 'uuid',
                Rule::exists(Account::class, 'id')->where('company_id', $companyId)->whereIn('subtype', ['cash', 'bank'])->where('is_active', true),
            ],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $intoTank = (float) $this->input('quantity') - (float) $this->input('direct_quantity', 0);
            if ($intoTank > 0.0005 && ! $this->input('tank_id')) {
                $validator->errors()->add('tank_id', 'Pick the tank it went into.');
            }
            $closed = Transaction::where('company_id', app(CurrentCompany::class)->get()->id)
                ->where('transaction_type', 'fuel_daily_close')
                ->whereIn('status', ['posted', 'locked'])
                ->whereNull('deleted_at')->whereNull('reversed_by_id')
                ->whereDate('transaction_date', $this->input('date'))
                ->exists();
            if ($closed) {
                $validator->errors()->add('date', Carbon::parse($this->input('date'))->format('j M').' is already closed. Add it in that day\'s close (Edit day).');
            }
        }];
    }
}
