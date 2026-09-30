<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * A correction to a bill or a bill payment, from its page. See BillCorrectionService. The
 * party field stays `customer_id` in the payload (the dialog is shared with the AR side);
 * the controller maps it to a vendor before dispatching.
 */
class StoreBillCorrectionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('payment') ? Permissions::BILL_PAY : Permissions::BILL_UPDATE;

        return $this->hasCompanyPermission($permission) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in($this->route('payment') ? ['change_customer'] : ['change_customer', 'split'])],
            'customer_id' => ['required_if:action,change_customer', 'nullable', 'uuid'],
            'shares' => ['exclude_unless:action,split', 'required', 'array', 'min:2'],
            'shares.*.customer_id' => ['exclude_unless:action,split', 'required', 'uuid'],
            'shares.*.amount' => ['exclude_unless:action,split', 'required', 'numeric', 'min:0.01'],
            'apply_oldest_first' => ['nullable', 'boolean'],
            'unapply_payments' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
