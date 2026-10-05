<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::STOCK_ADJUST)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'uuid', Rule::exists(Warehouse::class, 'id')],
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')],
            'quantity' => 'required|numeric|not_in:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => ['required', Rule::in(array_keys(StockAdjustmentService::REASONS))],
            'expense_account_id' => [
                'nullable', 'required_if:reason,own_use', 'uuid',
                Rule::exists(Account::class, 'id')->where('type', 'expense'),
            ],
            'notes' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $reason = $this->input('reason');
            $qty = (float) $this->input('quantity');
            if (! isset(StockAdjustmentService::REASONS[$reason]) || $qty == 0.0) {
                return;
            }
            if (StockAdjustmentService::REASONS[$reason][1] !== ($qty > 0 ? 'increase' : 'decrease')) {
                $v->errors()->add('reason', 'Pick a reason that fits.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Pick why the stock changed.',
            'expense_account_id.required_if' => 'Pick the expense account.',
            'quantity.not_in' => 'The adjustment quantity cannot be zero.',
        ];
    }
}
