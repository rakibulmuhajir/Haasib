<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class SaveHotelConfirmationRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_UPDATE;
    }

    public function authorize(): bool
    {
        return parent::authorize() && ! app(TravelAccessService::class)->isAgentMember(app(CurrentCompany::class)->get()->id, $this->user());
    }

    public function rules(): array
    {
        return [
            'stay_id' => ['required', 'uuid'],
            'revision' => ['required', 'string', 'size:64'],
            'version' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:500'],
            'supplier_acknowledgement' => ['required_if:status,cancelled', 'nullable', 'string', 'max:500'],
            'brn' => ['nullable', 'string', 'max:100'],
            'confirmation_number' => ['nullable', 'string', 'max:100'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
