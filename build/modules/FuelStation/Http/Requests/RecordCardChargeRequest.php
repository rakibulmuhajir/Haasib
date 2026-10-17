<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class RecordCardChargeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'channel_code' => ['required', 'string', 'max:50'],
            'charge_date' => ['required', 'date'],
            // The card sales it covers; empty means the month of charge_date.
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
