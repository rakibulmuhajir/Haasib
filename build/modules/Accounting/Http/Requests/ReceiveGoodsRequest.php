<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ReceiveGoodsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'warehouse_id' => 'nullable|uuid',
            'lines' => 'nullable|array',
            'lines.*.line_id' => 'required_with:lines|uuid',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.expected_quantity' => 'nullable|numeric|min:0.01',
            'lines.*.received_quantity' => 'nullable|numeric|min:0.01',
            'lines.*.variance_reason' => 'nullable|string|in:transit_loss,spillage,temperature_adjustment,measurement_error,other',
            'lines.*.warehouse_id' => 'nullable|uuid',
            'lines.*.notes' => 'nullable|string|max:1000',
        ];
    }
}
