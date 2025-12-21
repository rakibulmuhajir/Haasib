<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreShiftCloseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift' => ['required', 'in:day,night'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'uuid'],
            'lines.*.liters_sold' => ['required', 'numeric', 'min:0'],
            'lines.*.sale_rate' => ['required', 'numeric', 'min:0'],

            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'easypaisa_amount' => ['nullable', 'numeric', 'min:0'],
            'jazzcash_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_transfer_amount' => ['nullable', 'numeric', 'min:0'],
            'card_swipe_amount' => ['nullable', 'numeric', 'min:0'],
            'parco_card_amount' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

