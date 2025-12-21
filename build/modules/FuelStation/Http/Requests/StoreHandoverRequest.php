<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\AttendantHandover;

class StoreHandoverRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::HANDOVER_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'attendant_id' => ['required', 'uuid', 'exists:auth.users,id'],
            'handover_date' => ['required', 'date'],
            'pump_id' => ['nullable', 'uuid', 'exists:fuel.pumps,id'],
            'shift' => ['required', 'in:' . implode(',', AttendantHandover::getShifts())],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'easypaisa_amount' => ['nullable', 'numeric', 'min:0'],
            'jazzcash_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_transfer_amount' => ['nullable', 'numeric', 'min:0'],
            'card_swipe_amount' => ['nullable', 'numeric', 'min:0'],
            'parco_card_amount' => ['nullable', 'numeric', 'min:0'],
            'destination_bank_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one amount is provided
            $amounts = [
                $this->cash_amount ?? 0,
                $this->easypaisa_amount ?? 0,
                $this->jazzcash_amount ?? 0,
                $this->bank_transfer_amount ?? 0,
                $this->card_swipe_amount ?? 0,
                $this->parco_card_amount ?? 0,
            ];

            if (array_sum($amounts) <= 0) {
                $validator->errors()->add('cash_amount', 'At least one payment channel amount must be greater than zero.');
            }
        });
    }
}
